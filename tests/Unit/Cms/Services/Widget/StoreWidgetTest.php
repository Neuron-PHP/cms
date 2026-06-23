<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\Store\CartService;
use Neuron\Cms\Services\Store\StoreService;
use Neuron\Cms\Services\Widget\StoreWidget;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

class StoreWidgetTest extends TestCase
{
	private function product( int $id, int $price = 2000, int $active = 1 ): array
	{
		return [
			'id'          => $id,
			'name'        => 'Product ' . $id,
			'slug'        => 'product-' . $id,
			'sku'         => 'SKU-' . $id,
			'description' => 'Description ' . $id,
			'price_cents' => $price,
			'currency'    => 'usd',
			'image_url'   => null,
			'active'      => $active
		];
	}

	private function widget( IProductRepository $products ): StoreWidget
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )->willReturn( null );

		$_SESSION = [];
		$cart = new CartService( $products, new SessionManager( [ 'test_mode' => true ] ) );

		return new StoreWidget( $products, new StoreService( $settings ), $cart );
	}

	public function testRenderProductsGrid(): void
	{
		$products = $this->createMock( IProductRepository::class );
		$products->method( 'allActive' )->willReturn( [ $this->product( 1 ), $this->product( 2 ) ] );

		$html = $this->widget( $products )->renderProducts( [] );

		$this->assertStringContainsString( 'Product 1', $html );
		$this->assertStringContainsString( 'Product 2', $html );
		$this->assertStringContainsString( 'action="/cart/add"', $html );
		$this->assertStringContainsString( '$20.00', $html );
	}

	public function testRenderProductsEmpty(): void
	{
		$products = $this->createMock( IProductRepository::class );
		$products->method( 'allActive' )->willReturn( [] );

		$html = $this->widget( $products )->renderProducts( [] );

		$this->assertStringContainsString( 'No products', $html );
	}

	public function testRenderSingleProductById(): void
	{
		$products = $this->createMock( IProductRepository::class );
		$products->method( 'findById' )->with( 5 )->willReturn( $this->product( 5, 3500 ) );

		$html = $this->widget( $products )->renderProduct( [ 'id' => 5 ] );

		$this->assertStringContainsString( 'Product 5', $html );
		$this->assertStringContainsString( '$35.00', $html );
		$this->assertStringContainsString( 'value="5"', $html );
	}

	public function testRenderSingleProductInactiveReturnsComment(): void
	{
		$products = $this->createMock( IProductRepository::class );
		$products->method( 'findById' )->willReturn( $this->product( 7, 2000, 0 ) );

		$html = $this->widget( $products )->renderProduct( [ 'id' => 7 ] );

		$this->assertStringContainsString( '<!-- store widget:', $html );
	}

	public function testRenderCartLinkShowsCount(): void
	{
		$products = $this->createMock( IProductRepository::class );
		$products->method( 'findById' )->willReturnCallback(
			fn( int $id ) => $id === 1 ? $this->product( 1, 1500 ) : null
		);

		$widget = $this->widget( $products );

		$html = $widget->renderCart( [] );

		$this->assertStringContainsString( '/cart', $html );
		$this->assertStringContainsString( 'View cart', $html );
	}
}
