<?php

namespace Tests\Cms\Admin;

use Neuron\Cms\Admin\Navigation;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\Components\IComponentProvider;
use Neuron\Mvc\Components\INavigationProvider;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;
use Neuron\Routing\RouteMap;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Registry::getInstance()->set( RegistryKeys::APP, null );
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, null );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::APP, null );
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, null );
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
		$this->assertSame( [], Navigation::items( 'content' ), 'items stay in their own group' );
	}

	public function testItemsWithoutARegisteredRouteAreDropped(): void
	{
		Registry::getInstance()->set( RegistryKeys::MVC_COMPONENTS, [ NavigationTestProvider::class ] );
		$this->appWithRoutes( [ 'admin_jobs', 'zed.index' ] );

		$items = Navigation::items( Navigation::GROUP_SYSTEM );

		$this->assertSame( [ 'Scheduled Jobs', 'Zed' ], array_column( $items, 'label' ) );
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
