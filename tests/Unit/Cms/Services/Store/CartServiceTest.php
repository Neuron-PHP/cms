<?php

namespace Tests\Unit\Cms\Services\Store;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\Store\CartService;
use PHPUnit\Framework\TestCase;

class CartServiceTest extends TestCase
{
	private SessionManager $session;

	protected function setUp(): void
	{
		$_SESSION = [];
		$this->session = new SessionManager( [ 'test_mode' => true ] );
	}

	/**
	 * Product repository stub backed by an in-memory catalog.
	 */
	private function products( array $catalog ): IProductRepository
	{
		$repo = $this->createMock( IProductRepository::class );
		$repo->method( 'findById' )->willReturnCallback(
			static fn( int $id ) => $catalog[ $id ] ?? null
		);

		return $repo;
	}

	private function product( int $id, int $price, int $active = 1 ): array
	{
		return [
			'id'          => $id,
			'name'        => 'Product ' . $id,
			'slug'        => 'product-' . $id,
			'sku'         => 'SKU-' . $id,
			'price_cents' => $price,
			'currency'    => 'usd',
			'active'      => $active
		];
	}

	public function testAddAccumulatesQuantity(): void
	{
		$cart = new CartService( $this->products( [ 1 => $this->product( 1, 1000 ) ] ), $this->session );

		$cart->add( 1, 2 );
		$cart->add( 1, 3 );

		$this->assertSame( [ 1 => 5 ], $cart->rawItems() );
		$this->assertSame( 5, $cart->totalQuantity() );
	}

	public function testSetQuantityZeroRemoves(): void
	{
		$cart = new CartService( $this->products( [ 1 => $this->product( 1, 1000 ) ] ), $this->session );

		$cart->add( 1, 2 );
		$cart->setQuantity( 1, 0 );

		$this->assertTrue( $cart->isEmpty() );
	}

	public function testRemoveAndClear(): void
	{
		$cart = new CartService( $this->products( [
			1 => $this->product( 1, 1000 ),
			2 => $this->product( 2, 500 )
		] ), $this->session );

		$cart->add( 1, 1 );
		$cart->add( 2, 1 );
		$cart->remove( 1 );

		$this->assertSame( [ 2 => 1 ], $cart->rawItems() );

		$cart->clear();
		$this->assertTrue( $cart->isEmpty() );
	}

	public function testResolveComputesTotalsFromLivePrices(): void
	{
		$cart = new CartService( $this->products( [
			1 => $this->product( 1, 2000 ),
			2 => $this->product( 2, 500 )
		] ), $this->session );

		$cart->add( 1, 2 );
		$cart->add( 2, 1 );

		$resolved = $cart->resolve();

		$this->assertCount( 2, $resolved['items'] );
		$this->assertSame( 4500, $resolved['total_cents'] );
		$this->assertSame( 3, $resolved['count'] );
		$this->assertSame( 4000, $resolved['items'][0]['line_total_cents'] );
	}

	public function testResolveDropsInactiveOrMissingProducts(): void
	{
		$cart = new CartService( $this->products( [
			1 => $this->product( 1, 2000 ),
			2 => $this->product( 2, 500, 0 ) // inactive
		] ), $this->session );

		$cart->add( 1, 1 );
		$cart->add( 2, 1 );
		$cart->add( 3, 1 ); // missing from catalog

		$resolved = $cart->resolve();

		$this->assertCount( 1, $resolved['items'] );
		$this->assertSame( 2000, $resolved['total_cents'] );
		// The pruned items should no longer be in the cart.
		$this->assertSame( [ 1 => 1 ], $cart->rawItems() );
	}
}
