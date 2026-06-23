<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseProductRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseProductRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseProductRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE products (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL,
				sku VARCHAR(64),
				description TEXT,
				price_cents INTEGER NOT NULL DEFAULT 0,
				currency VARCHAR(8) NOT NULL DEFAULT 'usd',
				image_url VARCHAR(512),
				active INTEGER NOT NULL DEFAULT 1,
				sort_order INTEGER NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		$this->repository = new DatabaseProductRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function sampleRow( array $overrides = [] ): array
	{
		return array_merge( [
			'name'        => 'T-Shirt',
			'slug'        => 't-shirt',
			'sku'         => 'TS-1',
			'description' => 'A nice shirt',
			'price_cents' => 2000,
			'currency'    => 'usd',
			'image_url'   => null,
			'active'      => 1,
			'sort_order'  => 0
		], $overrides );
	}

	public function testCreateAndFindById(): void
	{
		$id  = $this->repository->create( $this->sampleRow() );
		$row = $this->repository->findById( $id );

		$this->assertSame( 'T-Shirt', $row['name'] );
		$this->assertSame( 2000, (int) $row['price_cents'] );
	}

	public function testFindBySlug(): void
	{
		$this->repository->create( $this->sampleRow( [ 'slug' => 'mug' ] ) );

		$this->assertNotNull( $this->repository->findBySlug( 'mug' ) );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testUpdateChangesFields(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->update( $id, [ 'name' => 'Premium Tee', 'price_cents' => 3500 ] ) );

		$row = $this->repository->findById( $id );
		$this->assertSame( 'Premium Tee', $row['name'] );
		$this->assertSame( 3500, (int) $row['price_cents'] );
	}

	public function testAllActiveExcludesInactiveAndSorts(): void
	{
		$this->repository->create( $this->sampleRow( [ 'slug' => 'b', 'name' => 'B', 'sort_order' => 2 ] ) );
		$this->repository->create( $this->sampleRow( [ 'slug' => 'a', 'name' => 'A', 'sort_order' => 1 ] ) );
		$this->repository->create( $this->sampleRow( [ 'slug' => 'hidden', 'name' => 'Hidden', 'active' => 0 ] ) );

		$active = $this->repository->allActive();

		$this->assertCount( 2, $active );
		$this->assertSame( 'A', $active[0]['name'] );
		$this->assertSame( 'B', $active[1]['name'] );
	}

	public function testAllActiveRespectsLimit(): void
	{
		$this->repository->create( $this->sampleRow( [ 'slug' => 'a', 'sort_order' => 1 ] ) );
		$this->repository->create( $this->sampleRow( [ 'slug' => 'b', 'sort_order' => 2 ] ) );

		$this->assertCount( 1, $this->repository->allActive( 1 ) );
	}

	public function testDeleteRemovesRow(): void
	{
		$id = $this->repository->create( $this->sampleRow() );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->findById( $id ) );
	}

	public function testPaginate(): void
	{
		$this->repository->create( $this->sampleRow( [ 'slug' => 'a' ] ) );
		$this->repository->create( $this->sampleRow( [ 'slug' => 'b' ] ) );

		$result = $this->repository->paginate( 1, 25 );
		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );
	}
}
