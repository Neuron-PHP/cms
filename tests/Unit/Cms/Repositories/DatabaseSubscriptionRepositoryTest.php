<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseSubscriptionRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseSubscriptionRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseSubscriptionRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE subscriptions (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				purpose VARCHAR(32) NOT NULL DEFAULT 'donation',
				form_key VARCHAR(64) NOT NULL,
				provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
				subscription_id VARCHAR(255) NOT NULL,
				status VARCHAR(32) NOT NULL DEFAULT 'active',
				frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
				amount_cents INTEGER NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				payer_name VARCHAR(255),
				payer_email VARCHAR(255),
				payload TEXT NOT NULL DEFAULT '{}',
				current_period_end TIMESTAMP,
				canceled_at TIMESTAMP,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$this->repository = new DatabaseSubscriptionRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sampleRow( array $overrides = [] ): array
	{
		return array_merge( [
			'purpose'            => 'donation',
			'form_key'           => 'general',
			'provider'           => 'stripe',
			'subscription_id'    => 'sub_1',
			'status'             => 'active',
			'frequency'          => 'monthly',
			'amount_cents'       => 2500,
			'currency'           => 'usd',
			'payer_name'         => 'Alice',
			'payer_email'        => 'alice@example.com',
			'payload'            => '{}',
			'current_period_end' => null
		], $overrides );
	}

	public function testCreateAndFindByGatewayId(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertGreaterThan( 0, $id );

		$row = $this->repository->findByGatewayId( 'sub_1' );
		$this->assertSame( 'active', $row['status'] );
		$this->assertSame( 'monthly', $row['frequency'] );
		$this->assertNull( $this->repository->findByGatewayId( 'sub_missing' ) );
	}

	public function testUpdateStateChangesStatusAndPeriod(): void
	{
		$this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->updateState( 'sub_1', [
			'status'             => 'past_due',
			'current_period_end' => '2026-07-01 00:00:00'
		] ) );

		$row = $this->repository->findByGatewayId( 'sub_1' );
		$this->assertSame( 'past_due', $row['status'] );
		$this->assertSame( '2026-07-01 00:00:00', $row['current_period_end'] );
		$this->assertNotEmpty( $row['updated_at'] );
	}

	public function testUpdateStateCancel(): void
	{
		$this->repository->create( $this->sampleRow() );

		$this->repository->updateState( 'sub_1', [
			'status'      => 'canceled',
			'canceled_at' => '2026-06-22 12:00:00'
		] );

		$row = $this->repository->findByGatewayId( 'sub_1' );
		$this->assertSame( 'canceled', $row['status'] );
		$this->assertSame( '2026-06-22 12:00:00', $row['canceled_at'] );
	}

	public function testPaginateFilters(): void
	{
		$this->repository->create( $this->sampleRow( [ 'subscription_id' => 'sub_a', 'status' => 'active' ] ) );
		$this->repository->create( $this->sampleRow( [ 'subscription_id' => 'sub_b', 'status' => 'canceled', 'form_key' => 'membership' ] ) );

		$this->assertSame( 1, $this->repository->paginate( 1, 25, 'active' )['total'] );
		$this->assertSame( 1, $this->repository->paginate( 1, 25, null, 'membership' )['total'] );
		$this->assertSame( 2, $this->repository->paginate( 1, 25 )['total'] );
	}
}
