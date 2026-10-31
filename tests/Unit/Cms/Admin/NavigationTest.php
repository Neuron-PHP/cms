<?php

namespace Tests\Cms\Admin;

use Neuron\Cms\Admin\Navigation;
use Neuron\Cms\Features\Features;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\Components\IComponentProvider;
use Neuron\Mvc\Components\INavigationProvider;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
	private mixed $_previousSettings;

	protected function setUp(): void
	{
		parent::setUp();
		$this->_previousSettings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		Registry::getInstance()->set( RegistryKeys::APP, null );
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, null );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::APP, null );
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, null );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->_previousSettings );
		parent::tearDown();
	}

	/**
	 * Put an application in the registry whose router knows only these routes.
	 *
	 * @param list<string> $routes
	 */
	private function appWithRoutes( array $routes ): void
	{
		$router = $this->createMock( Router::class );
		$router->method( 'getRouteByName' )->willReturnCallback(
			fn( string $name ) => in_array( $name, $routes, true ) ? $this->createMock( RouteMap::class ) : null
		);

		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $router );

		Registry::getInstance()->set( RegistryKeys::APP, $app );
	}

	public function testSystemGroupHasScheduledJobsByDefault(): void
	{
		$items = Navigation::items( Navigation::GROUP_SYSTEM );

		$this->assertCount( 1, $items );
		$this->assertSame( 'Scheduled Jobs', $items[0]['label'] );
		$this->assertSame( 'admin_jobs', $items[0]['route'] );
		$this->assertSame( 'bi-clock-history', $items[0]['icon'] );
		$this->assertNull( $items[0]['provider'] );
	}

	public function testUnknownGroupIsEmpty(): void
	{
		$this->assertSame( [], Navigation::items( 'nope' ) );
	}

	public function testComponentItemsAreMergedAndSorted(): void
	{
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, [ NavigationTestProvider::class ] );

		$items = Navigation::items( Navigation::GROUP_SYSTEM );

		$this->assertSame( [ 'Labor Board', 'Scheduled Jobs', 'Zed' ], array_column( $items, 'label' ) );
		$this->assertSame( NavigationTestProvider::class, $items[0]['provider'] );
		$this->assertNull( $items[1]['provider'] );
		$this->assertSame(
			[],
			array_filter( Navigation::items( Navigation::GROUP_CONTENT ), fn( array $item ) => $item['provider'] !== null ),
			'items stay in their own group'
		);
	}

	public function testItemsWithoutARegisteredRouteAreDropped(): void
	{
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, [ NavigationTestProvider::class ] );
		$this->appWithRoutes( [ 'admin_jobs', 'zed.index' ] );

		$items = Navigation::items( Navigation::GROUP_SYSTEM );

		$this->assertSame( [ 'Scheduled Jobs', 'Zed' ], array_column( $items, 'label' ) );
	}

	public function testContentGroupListsEveryAreaByDefault(): void
	{
		$items = Navigation::items( Navigation::GROUP_CONTENT );
		$routes = array_column( $items, 'route' );

		$this->assertSame( 'admin_posts', $routes[0] );
		$this->assertContains( 'admin_events', $routes );
		$this->assertContains( 'admin_products', $routes );
		$this->assertSame( 'admin_redirects', end( $routes ) );
		$this->assertSame( 'content', $items[0]['section'] );
	}

	public function testContentGroupLeavesOutDisabledAreas(): void
	{
		Registry::getInstance()->set(
			RegistryKeys::SETTINGS,
			new SettingManager( new Memory( [ 'features' => [ Features::BLOG => false, Features::STORE => false ] ] ) )
		);

		$routes = array_column( Navigation::items( Navigation::GROUP_CONTENT ), 'route' );

		foreach( [ 'admin_posts', 'admin_categories', 'admin_tags', 'admin_products', 'admin_orders' ] as $hidden )
		{
			$this->assertNotContains( $hidden, $routes );
		}

		foreach( [ 'admin_pages', 'admin_media', 'admin_events', 'admin_payments', 'admin_menus', 'admin_redirects' ] as $shown )
		{
			$this->assertContains( $shown, $routes );
		}
	}

	public function testBuiltInItemIsDroppedWhenItsRouteIsMissing(): void
	{
		$this->appWithRoutes( [] );

		$this->assertSame( [], Navigation::items( Navigation::GROUP_SYSTEM ) );
	}
}

class NavigationTestProvider implements IComponentProvider, INavigationProvider
{
	public static function controllerPaths(): array
	{
		return [];
	}

	public static function viewPaths(): array
	{
		return [];
	}

	public static function register( IMvcApplication $app ): void
	{
	}

	public static function navigation(): array
	{
		return [
			[ 'group' => 'system', 'label' => 'Zed', 'route' => 'zed.index', 'order' => 10 ],
			[ 'group' => 'system', 'label' => 'Labor Board', 'route' => 'labor_board.index', 'icon' => 'bi-stack', 'order' => 5 ],
		];
	}
}
