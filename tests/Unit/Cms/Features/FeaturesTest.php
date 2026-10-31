<?php

namespace Tests\Cms\Features;

use Neuron\Cms\Controllers\Admin\Products;
use Neuron\Cms\Controllers\Blog;
use Neuron\Cms\Controllers\Pages;
use Neuron\Cms\Controllers\Payments;
use Neuron\Cms\Features\Features;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use Neuron\Routing\RouteMap;
use PHPUnit\Framework\TestCase;

class FeaturesTest extends TestCase
{
	private mixed $_previousSettings;

	protected function setUp(): void
	{
		parent::setUp();
		$this->_previousSettings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->_previousSettings );
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $features
	 */
	private function features( array $features ): Features
	{
		return new Features( new SettingManager( new Memory( [ 'features' => $features ] ) ) );
	}

	private function route( string $controller, string $name = '' ): RouteMap
	{
		$route = new RouteMap( '/x', fn() => null );
		$route->Payload = [ 'Controller' => $controller ];
		$route->Name = $name;

		return $route;
	}

	public function testEverythingIsOnWithoutSettings(): void
	{
		$features = new Features();

		foreach( Features::names() as $name )
		{
			$this->assertTrue( $features->isEnabled( $name ), $name );
		}
	}

	public function testMissingKeysCountAsOn(): void
	{
		$features = $this->features( [ 'store' => false ] );

		$this->assertTrue( $features->isEnabled( Features::BLOG ) );
		$this->assertFalse( $features->isEnabled( Features::STORE ) );
	}

	public function testFalseLikeValuesTurnAnAreaOff(): void
	{
		foreach( [ false, 0, '0', 'false', 'off', 'no', ' OFF ' ] as $value )
		{
			$this->assertFalse( $this->features( [ 'blog' => $value ] )->isEnabled( Features::BLOG ), var_export( $value, true ) );
		}

		foreach( [ true, 1, '1', 'true', 'on', 'yes' ] as $value )
		{
			$this->assertTrue( $this->features( [ 'blog' => $value ] )->isEnabled( Features::BLOG ), var_export( $value, true ) );
		}
	}

	public function testRoutesOfADisabledAreaAreRefused(): void
	{
		$features = $this->features( [ 'blog' => false, 'store' => false ] );

		$this->assertFalse( $features->allowsRoute( $this->route( Blog::class . '@index', 'blog' ) ) );
		$this->assertFalse( $features->allowsRoute( $this->route( '\\' . Products::class . '@index', 'admin_products' ) ) );
	}

	public function testRoutesOutsideOptionalAreasAreAlwaysAllowed(): void
	{
		$features = $this->features( array_fill_keys( Features::names(), false ) );

		$this->assertTrue( $features->allowsRoute( $this->route( Pages::class . '@show', 'page' ) ) );
		$this->assertTrue( $features->allowsRoute( $this->route( 'App\\Controllers\\Blog@index', 'blog' ) ), 'site controllers are not gated' );
		$this->assertTrue( $features->allowsRoute( $this->route( '' ) ) );
	}

	public function testPaymentsWebhookStaysOpenWhileTheStoreIsOn(): void
	{
		$webhook = $this->route( Payments::class . '@webhook', 'payments_webhook' );
		$checkout = $this->route( Payments::class . '@checkout', 'payments_checkout' );

		$storeOnly = $this->features( [ 'payments' => false ] );
		$this->assertTrue( $storeOnly->allowsRoute( $webhook ) );
		$this->assertFalse( $storeOnly->allowsRoute( $checkout ) );

		$neither = $this->features( [ 'payments' => false, 'store' => false ] );
		$this->assertFalse( $neither->allowsRoute( $webhook ) );
	}

	public function testShortcodesFollowTheirArea(): void
	{
		$features = $this->features( [ 'calendar' => false ] );

		$this->assertFalse( $features->allowsShortcode( 'calendar' ) );
		$this->assertFalse( $features->allowsShortcode( 'featured-event' ) );
		$this->assertTrue( $features->allowsShortcode( 'latest-posts' ) );
		$this->assertTrue( $features->allowsShortcode( 'something-else' ), 'unknown shortcodes are not ours to block' );
	}

	public function testUnknownKeysAreReported(): void
	{
		$this->assertSame( [ 'shop' ], $this->features( [ 'blog' => true, 'shop' => false ] )->unknownKeys() );
		$this->assertSame( [], ( new Features() )->unknownKeys() );
	}

	public function testCurrentReadsSettingsFromTheRegistry(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( new Memory( [ 'features' => [ 'faqs' => false ] ] ) ) );

		$this->assertFalse( Features::current()->isEnabled( Features::FAQS ) );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		$this->assertTrue( Features::current()->isEnabled( Features::FAQS ) );
	}
}
