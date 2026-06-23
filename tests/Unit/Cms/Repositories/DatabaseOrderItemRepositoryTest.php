<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseOrderItemRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseOrderItemRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseOrderItemRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE order_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				payment_id INTEGER NOT NULL,
				product_id INTEGER,
				name VARCHAR(255) NOT NULL,
				sku VARCHAR(64),
				unit_amount_cents INTEGER NOT NULL DEFAULT 0,
				quantity INTEGER NOT NULL DEFAULT 1,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$this->repository = new DatabaseOrderItemRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	public function testCreateForOrderAndFindByPaymentId(): void
	{
		$this->repository->createForOrder( 42, [
			[ 'product_id' => 1, 'name' => 'T-Shirt', 'sku' => 'TS-1', 'unit_amount_cents' => 2000, 'quantity' => 2, 'currency' => 'usd' ],
			[ 'product_id' => 2, 'name' => 'Mug', 'sku' => null, 'unit_amount_cents' => 1000, 'quantity' => 1, 'currency' => 'usd' ]
		] );

		// A line for a different order should not leak in.
		$this->repository->createForOrder( 99, [
			[ 'product_id' => 3, 'name' => 'Sticker', 'unit_amount_cents' => 500, 'quantity' => 5, 'currency' => 'usd' ]
		] );

		$items = $this->repository->findByPaymentId( 42 );

		$this->assertCount( 2, $items );
		$this->assertSame( 'T-Shirt', $items[0]['name'] );
		$this->assertSame( 2, (int) $items[0]['quantity'] );
		$this->assertSame( 'Mug', $items[1]['name'] );
	}

	public function testFindByPaymentIdEmpty(): void
	{
		$this->assertSame( [], $this->repository->findByPaymentId( 1234 ) );
	}
}
