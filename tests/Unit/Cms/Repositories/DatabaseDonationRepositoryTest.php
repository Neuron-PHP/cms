<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseDonationRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseDonationRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseDonationRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE donations (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				form_key VARCHAR(64) NOT NULL,
				provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
				session_id VARCHAR(255),
				payment_intent_id VARCHAR(255),
				subscription_id VARCHAR(255),
				amount_cents INTEGER NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				frequency VARCHAR(32) NOT NULL DEFAULT 'one_time',
				status VARCHAR(32) NOT NULL DEFAULT 'pending',
				donor_name VARCHAR(255),
				donor_email VARCHAR(255),
				payload TEXT NOT NULL DEFAULT '{}',
				ip_address VARCHAR(45),
				user_agent VARCHAR(500),
				completed_at TIMESTAMP,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$this->repository = new DatabaseDonationRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sampleRow( string $formKey = 'general', string $status = 'pending', ?string $sessionId = null ): array
	{
		return [
			'form_key'     => $formKey,
			'provider'     => 'stripe',
			'session_id'   => $sessionId,
			'amount_cents' => 5000,
			'currency'     => 'usd',
			'frequency'    => 'one_time',
			'status'       => $status,
			'donor_name'   => 'Alice',
			'donor_email'  => 'alice@example.com',
			'payload'      => json_encode( [ 'name' => 'Alice' ] ),
			'ip_address'   => '127.0.0.1',
			'user_agent'   => 'PHPUnit'
		];
	}

	public function testCreateReturnsInsertedId(): void
	{
		$this->assertGreaterThan( 0, $this->repository->create( $this->sampleRow() ) );
	}

	public function testFindByIdReturnsStoredRow(): void
	{
		$id  = $this->repository->create( $this->sampleRow() );
		$row = $this->repository->findById( $id );

		$this->assertIsArray( $row );
		$this->assertSame( 'general', $row['form_key'] );
		$this->assertSame( 5000, (int) $row['amount_cents'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	public function testFindBySessionId(): void
	{
		$this->repository->create( $this->sampleRow( 'general', 'pending', 'cs_test_abc' ) );

		$row = $this->repository->findBySessionId( 'cs_test_abc' );

		$this->assertIsArray( $row );
		$this->assertSame( 'cs_test_abc', $row['session_id'] );
		$this->assertNull( $this->repository->findBySessionId( 'cs_missing' ) );
	}

	public function testMarkCompletedStoresIdentifiers(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->markCompleted( $id, [
			'payment_intent_id' => 'pi_test_1',
			'amount_cents'      => 7500
		] ) );

		$row = $this->repository->findById( $id );
		$this->assertSame( 'completed', $row['status'] );
		$this->assertSame( 'pi_test_1', $row['payment_intent_id'] );
		$this->assertSame( 7500, (int) $row['amount_cents'] );
		$this->assertNotEmpty( $row['completed_at'] );
	}

	public function testUpdateStatusAndSession(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->updateStatus( $id, 'failed' ) );
		$this->assertSame( 'failed', $this->repository->findById( $id )['status'] );

		$this->assertTrue( $this->repository->updateSession( $id, 'cs_new' ) );
		$this->assertSame( 'cs_new', $this->repository->findById( $id )['session_id'] );
	}

	public function testDeleteRemovesRow(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->findById( $id ) );
	}

	public function testPaginateFiltersByStatusAndForm(): void
	{
		$this->repository->create( $this->sampleRow( 'general', 'completed' ) );
		$this->repository->create( $this->sampleRow( 'capital', 'pending' ) );
		$this->repository->create( $this->sampleRow( 'general', 'pending' ) );

		$this->assertSame( 1, $this->repository->paginate( 1, 25, 'completed' )['total'] );
		$this->assertSame( 2, $this->repository->paginate( 1, 25, null, 'general' )['total'] );
		$this->assertSame( 1, $this->repository->paginate( 1, 25, 'pending', 'general' )['total'] );
	}

	public function testFormKeysReturnsDistinct(): void
	{
		$this->repository->create( $this->sampleRow( 'general' ) );
		$this->repository->create( $this->sampleRow( 'capital' ) );
		$this->repository->create( $this->sampleRow( 'general' ) );

		$keys = $this->repository->formKeys();
		sort( $keys );

		$this->assertSame( [ 'capital', 'general' ], $keys );
	}
}
