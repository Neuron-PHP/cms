<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Models\EventRegistration;
use Neuron\Cms\Repositories\DatabaseEventRegistrationRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseEventRegistrationRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseEventRegistrationRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE event_registrations (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				event_id INTEGER NOT NULL,
				occurrence_date TIMESTAMP,
				user_id INTEGER,
				name VARCHAR(255) NOT NULL,
				email VARCHAR(255) NOT NULL,
				notes TEXT,
				status VARCHAR(20) NOT NULL DEFAULT 'registered',
				ip_address VARCHAR(45),
				user_agent VARCHAR(500),
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name'    => ':memory:'
			] );

		$this->repository = new DatabaseEventRegistrationRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sample( int $eventId = 1, string $email = 'alice@example.com', ?int $userId = null ): EventRegistration
	{
		$registration = new EventRegistration();
		$registration->setEventId( $eventId );
		$registration->setUserId( $userId );
		$registration->setName( 'Alice' );
		$registration->setEmail( $email );
		$registration->setNotes( 'See you there' );
		$registration->setStatus( EventRegistration::STATUS_REGISTERED );
		$registration->setIpAddress( '127.0.0.1' );
		$registration->setUserAgent( 'PHPUnit' );

		return $registration;
	}

	public function testCreateReturnsRegistrationWithId(): void
	{
		$registration = $this->repository->create( $this->sample() );

		$this->assertGreaterThan( 0, $registration->getId() );
	}

	public function testFindByIdReturnsStoredRegistration(): void
	{
		$id = $this->repository->create( $this->sample() )->getId();

		$found = $this->repository->findById( $id );

		$this->assertInstanceOf( EventRegistration::class, $found );
		$this->assertSame( 'Alice', $found->getName() );
		$this->assertSame( 'alice@example.com', $found->getEmail() );
		$this->assertSame( 1, $found->getEventId() );
	}

	public function testFindByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findById( 999 ) );
	}

	public function testGetByEventReturnsRegistrationsForEvent(): void
	{
		$this->repository->create( $this->sample( 1, 'a@example.com' ) );
		$this->repository->create( $this->sample( 1, 'b@example.com' ) );
		$this->repository->create( $this->sample( 2, 'c@example.com' ) );

		$rows = $this->repository->getByEvent( 1 );

		$this->assertCount( 2, $rows );
		$this->assertInstanceOf( EventRegistration::class, $rows[0] );
	}

	public function testCountByEventCountsOnlyRegisteredStatus(): void
	{
		$this->repository->create( $this->sample( 1, 'a@example.com' ) );

		$cancelled = $this->sample( 1, 'b@example.com' );
		$cancelled->setStatus( EventRegistration::STATUS_CANCELLED );
		$this->repository->create( $cancelled );

		$this->assertSame( 1, $this->repository->countByEvent( 1 ) );
	}

	public function testExistsForEmailDetectsDuplicate(): void
	{
		$this->repository->create( $this->sample( 1, 'dupe@example.com' ) );

		$this->assertTrue( $this->repository->existsForEmail( 1, 'dupe@example.com' ) );
		$this->assertFalse( $this->repository->existsForEmail( 1, 'other@example.com' ) );
		$this->assertFalse( $this->repository->existsForEmail( 2, 'dupe@example.com' ) );
	}

	public function testPaginateReturnsItemsAndMeta(): void
	{
		$this->repository->create( $this->sample( 1, 'a@example.com' ) );
		$this->repository->create( $this->sample( 2, 'b@example.com' ) );
		$this->repository->create( $this->sample( 1, 'c@example.com' ) );

		$result = $this->repository->paginate( 1, 2 );

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 2, $result['pages'] );
		$this->assertCount( 2, $result['items'] );
		$this->assertInstanceOf( EventRegistration::class, $result['items'][0] );
	}

	public function testPaginateFiltersByEvent(): void
	{
		$this->repository->create( $this->sample( 1, 'a@example.com' ) );
		$this->repository->create( $this->sample( 2, 'b@example.com' ) );
		$this->repository->create( $this->sample( 1, 'c@example.com' ) );

		$result = $this->repository->paginate( 1, 25, 1 );

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}

	public function testDeleteRemovesRegistration(): void
	{
		$id = $this->repository->create( $this->sample() )->getId();

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->findById( $id ) );
	}

	public function testOccurrenceDateIsPersisted(): void
	{
		$registration = $this->sample( 1, 'occ@example.com' );
		$registration->setOccurrenceDate( new \DateTimeImmutable( '2026-01-12 09:00:00' ) );

		$id = $this->repository->create( $registration )->getId();
		$found = $this->repository->findById( $id );

		$this->assertNotNull( $found->getOccurrenceDate() );
		$this->assertSame( '2026-01-12 09:00:00', $found->getOccurrenceDate()->format( 'Y-m-d H:i:s' ) );
	}

	public function testCountByEventScopesToOccurrence(): void
	{
		$jan12 = new \DateTimeImmutable( '2026-01-12 09:00:00' );
		$jan19 = new \DateTimeImmutable( '2026-01-19 09:00:00' );

		$a = $this->sample( 1, 'a@example.com' );
		$a->setOccurrenceDate( $jan12 );
		$this->repository->create( $a );

		$b = $this->sample( 1, 'b@example.com' );
		$b->setOccurrenceDate( $jan12 );
		$this->repository->create( $b );

		$c = $this->sample( 1, 'c@example.com' );
		$c->setOccurrenceDate( $jan19 );
		$this->repository->create( $c );

		$this->assertSame( 2, $this->repository->countByEvent( 1, $jan12 ) );
		$this->assertSame( 1, $this->repository->countByEvent( 1, $jan19 ) );
		$this->assertSame( 3, $this->repository->countByEvent( 1 ) );
	}

	public function testExistsForEmailScopesToOccurrence(): void
	{
		$jan12 = new \DateTimeImmutable( '2026-01-12 09:00:00' );
		$jan19 = new \DateTimeImmutable( '2026-01-19 09:00:00' );

		$registration = $this->sample( 1, 'dupe@example.com' );
		$registration->setOccurrenceDate( $jan12 );
		$this->repository->create( $registration );

		$this->assertTrue( $this->repository->existsForEmail( 1, 'dupe@example.com', $jan12 ) );
		$this->assertFalse( $this->repository->existsForEmail( 1, 'dupe@example.com', $jan19 ) );
	}
}
