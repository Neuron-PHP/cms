<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabasePaymentRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabasePaymentRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE payments (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				purpose VARCHAR(32) NOT NULL DEFAULT 'donation',
				form_key VARCHAR(64) NOT NULL,
				provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
				type VARCHAR(16) NOT NULL DEFAULT 'one_time',
				session_id VARCHAR(255),
				payment_intent_id VARCHAR(255),
				invoice_id VARCHAR(255),
				subscription_id VARCHAR(255),
				amount_cents INTEGER NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				frequency VARCHAR(32) NOT NULL DEFAULT 'one_time',
				status VARCHAR(32) NOT NULL DEFAULT 'pending',
				payer_name VARCHAR(255),
				payer_email VARCHAR(255),
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

		$this->repository = new DatabasePaymentRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sampleRow( array $overrides = [] ): array
	{
		return array_merge( [
			'purpose'      => 'donation',
			'form_key'     => 'general',
			'provider'     => 'stripe',
			'type'         => 'one_time',
			'session_id'   => null,
			'subscription_id' => null,
			'invoice_id'   => null,
			'amount_cents' => 5000,
			'currency'     => 'usd',
			'frequency'    => 'one_time',
			'status'       => 'pending',
			'payer_name'   => 'Alice',
			'payer_email'  => 'alice@example.com',
			'payload'      => json_encode( [ 'name' => 'Alice' ] ),
			'ip_address'   => '127.0.0.1',
			'user_agent'   => 'PHPUnit'
		], $overrides );
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
		$this->assertSame( 'donation', $row['purpose'] );
		$this->assertSame( 5000, (int) $row['amount_cents'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	public function testFindBySessionId(): void
	{
		$this->repository->create( $this->sampleRow( [ 'session_id' => 'cs_test_abc' ] ) );

		$row = $this->repository->findBySessionId( 'cs_test_abc' );

		$this->assertIsArray( $row );
		$this->assertSame( 'cs_test_abc', $row['session_id'] );
		$this->assertNull( $this->repository->findBySessionId( 'cs_missing' ) );
	}

	public function testFindBySubscriptionIdReturnsMostRecent(): void
	{
		$this->repository->create( $this->sampleRow( [ 'subscription_id' => 'sub_1', 'type' => 'recurring' ] ) );
		$latest = $this->repository->create( $this->sampleRow( [ 'subscription_id' => 'sub_1', 'type' => 'recurring' ] ) );

		$row = $this->repository->findBySubscriptionId( 'sub_1' );

		$this->assertSame( $latest, (int) $row['id'] );
		$this->assertNull( $this->repository->findBySubscriptionId( 'sub_missing' ) );
	}

	public function testFindByInvoiceId(): void
	{
		$this->repository->create( $this->sampleRow( [ 'invoice_id' => 'in_99' ] ) );

		$this->assertNotNull( $this->repository->findByInvoiceId( 'in_99' ) );
		$this->assertNull( $this->repository->findByInvoiceId( 'in_missing' ) );
	}

	public function testMarkCompletedStoresIdentifiers(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->markCompleted( $id, [
			'payment_intent_id' => 'pi_test_1',
			'subscription_id'   => 'sub_1',
			'amount_cents'      => 7500,
			'type'              => 'recurring'
		] ) );

		$row = $this->repository->findById( $id );
		$this->assertSame( 'completed', $row['status'] );
		$this->assertSame( 'pi_test_1', $row['payment_intent_id'] );
		$this->assertSame( 'sub_1', $row['subscription_id'] );
		$this->assertSame( 'recurring', $row['type'] );
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

	public function testPaginateFiltersByStatusFormAndPurpose(): void
	{
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'general', 'status' => 'completed' ] ) );
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'membership', 'status' => 'pending', 'purpose' => 'membership' ] ) );
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'general', 'status' => 'pending' ] ) );

		$this->assertSame( 1, $this->repository->paginate( 1, 25, 'completed' )['total'] );
		$this->assertSame( 2, $this->repository->paginate( 1, 25, null, 'general' )['total'] );
		$this->assertSame( 1, $this->repository->paginate( 1, 25, 'pending', 'general' )['total'] );
		$this->assertSame( 1, $this->repository->paginate( 1, 25, null, null, 'membership' )['total'] );
	}

	public function testFormKeysReturnsDistinct(): void
	{
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'general' ] ) );
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'membership' ] ) );
		$this->repository->create( $this->sampleRow( [ 'form_key' => 'general' ] ) );

		$keys = $this->repository->formKeys();
		sort( $keys );

		$this->assertSame( [ 'general', 'membership' ], $keys );
	}
}
