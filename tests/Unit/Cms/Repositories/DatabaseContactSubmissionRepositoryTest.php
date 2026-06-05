<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseContactSubmissionRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseContactSubmissionRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseContactSubmissionRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE contact_submissions (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				form_key VARCHAR(64) NOT NULL,
				recipient VARCHAR(255) NOT NULL,
				subject VARCHAR(255),
				reply_to_email VARCHAR(255),
				reply_to_name VARCHAR(255),
				payload TEXT NOT NULL DEFAULT '{}',
				ip_address VARCHAR(45),
				user_agent VARCHAR(500),
				delivered BOOLEAN DEFAULT 0,
				delivered_at TIMESTAMP,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name'    => ':memory:'
			] );

		$this->repository = new DatabaseContactSubmissionRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sampleRow( string $formKey = 'general', bool $delivered = false ): array
	{
		return [
			'form_key'       => $formKey,
			'recipient'      => 'info@example.com',
			'subject'        => 'Website Contact',
			'reply_to_email' => 'alice@example.com',
			'reply_to_name'  => 'Alice',
			'payload'        => json_encode( [ 'name' => 'Alice', 'message' => 'Hello' ] ),
			'ip_address'     => '127.0.0.1',
			'user_agent'     => 'PHPUnit',
			'delivered'      => $delivered
		];
	}

	public function testCreateReturnsInsertedId(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertGreaterThan( 0, $id );
	}

	public function testFindByIdReturnsStoredRow(): void
	{
		$id  = $this->repository->create( $this->sampleRow() );
		$row = $this->repository->findById( $id );

		$this->assertIsArray( $row );
		$this->assertSame( 'general', $row['form_key'] );
		$this->assertSame( 'info@example.com', $row['recipient'] );
		$this->assertSame( 0, (int) $row['delivered'] );
	}

	public function testFindByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findById( 999 ) );
	}

	public function testMarkDeliveredSetsFlagAndTimestamp(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->markDelivered( $id ) );

		$row = $this->repository->findById( $id );
		$this->assertSame( 1, (int) $row['delivered'] );
		$this->assertNotEmpty( $row['delivered_at'] );
	}

	public function testDeleteRemovesRow(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->findById( $id ) );
	}

	public function testPaginateReturnsItemsAndMeta(): void
	{
		$this->repository->create( $this->sampleRow( 'general' ) );
		$this->repository->create( $this->sampleRow( 'intake' ) );
		$this->repository->create( $this->sampleRow( 'general' ) );

		$result = $this->repository->paginate( 1, 2 );

		$this->assertSame( 3, $result['total'] );
		$this->assertSame( 2, $result['pages'] );
		$this->assertCount( 2, $result['items'] );
	}

	public function testPaginateFiltersByFormKey(): void
	{
		$this->repository->create( $this->sampleRow( 'general' ) );
		$this->repository->create( $this->sampleRow( 'intake' ) );
		$this->repository->create( $this->sampleRow( 'general' ) );

		$result = $this->repository->paginate( 1, 25, 'general' );

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}

	public function testFormKeysReturnsDistinctKeys(): void
	{
		$this->repository->create( $this->sampleRow( 'general' ) );
		$this->repository->create( $this->sampleRow( 'intake' ) );
		$this->repository->create( $this->sampleRow( 'general' ) );

		$keys = $this->repository->formKeys();

		sort( $keys );
		$this->assertSame( [ 'general', 'intake' ], $keys );
	}
}
