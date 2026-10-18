<?php

namespace Tests\Cms\View;

use Neuron\Cms\Services\Menu\MenuService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once __DIR__ . '/../../../../src/Cms/View/helpers.php';
	}

	public function testCmsMenuReturnsEmptyWhenAppIsMissing(): void
	{
		Registry::getInstance()->set( 'App', null );

		$this->assertSame( [], cms_menu( 'header' ) );
	}

	public function testCmsMenuReturnsItemsFromMenuService(): void
	{
		$items = [
			[ 'label' => 'Home', 'href' => '/', 'target' => '_self', 'children' => [] ]
		];

		$service = $this->createMock( MenuService::class );
		$service->expects( $this->once() )
			->method( 'forLocation' )
			->with( 'header' )
			->willReturn( $items );

		$container = $this->createMock( IContainer::class );
		$container->method( 'get' )
			->with( MenuService::class )
			->willReturn( $service );

		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getContainer' )->willReturn( $container );
		Registry::getInstance()->set( 'App', $app );

		$this->assertSame( $items, cms_menu( 'header' ) );
	}

	public function testCmsAbsoluteUrlPrefixesSiteUrl(): void
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )
			->with( 'site', 'url' )
			->willReturn( 'https://example.test' );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );
		Registry::getInstance()->set( 'App', null );

		$this->assertSame( 'https://example.test/', cms_absolute_url( 'home' ) );
	}

	public function testCmsBreadcrumbsRendersTrail(): void
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )->willReturn( null );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$html = cms_breadcrumbs( [
			[ 'label' => 'Home', 'url' => '/' ],
			[ 'label' => 'About', 'url' => null ]
		] );

		$this->assertStringContainsString( 'breadcrumb', $html );
		$this->assertStringContainsString( 'About', $html );
	}

	public function testCmsBreadcrumbsReturnsEmptyWithoutSettings(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( 'Settings', null );

		$this->assertSame( '', cms_breadcrumbs( [ [ 'label' => 'About', 'url' => null ] ] ) );
	}

	public function testCmsViewPathPrefersSiteCopy(): void
	{
		$site = sys_get_temp_dir() . '/cms_view_path_' . uniqid();
		mkdir( $site . '/partials', 0755, true );
		file_put_contents( $site . '/partials/navigation.php', 'site' );

		$previous = Registry::getInstance()->get( RegistryKeys::VIEWS_PATH );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $site, $this->packageViews() ] );

		try
		{
			$this->assertSame( $site . '/partials/navigation.php', cms_view_path( 'partials/navigation.php' ) );
		}
		finally
		{
			Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, $previous );
			unlink( $site . '/partials/navigation.php' );
			rmdir( $site . '/partials' );
			rmdir( $site );
		}
	}

	public function testCmsViewPathFallsBackToPackageWhenSiteLacksView(): void
	{
		$site = sys_get_temp_dir() . '/cms_view_path_' . uniqid();
		mkdir( $site, 0755, true );

		$previous = Registry::getInstance()->get( RegistryKeys::VIEWS_PATH );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $site, $this->packageViews() ] );

		try
		{
			$path = cms_view_path( 'partials/footer-navigation.php' );

			$this->assertSame( $this->packageViews() . '/partials/footer-navigation.php', $path );
			$this->assertFileExists( $path );
		}
		finally
		{
			Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, $previous );
			rmdir( $site );
		}
	}

	public function testCmsViewPathFallsBackToPackageWithoutRegisteredRoots(): void
	{
		$previousViews = Registry::getInstance()->get( RegistryKeys::VIEWS_PATH );
		$previousBase = Registry::getInstance()->get( RegistryKeys::BASE_PATH );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, null );
		Registry::getInstance()->set( RegistryKeys::BASE_PATH, null );

		try
		{
			$this->assertFileExists( cms_view_path( 'partials/navigation.php' ) );
		}
		finally
		{
			Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, $previousViews );
			Registry::getInstance()->set( RegistryKeys::BASE_PATH, $previousBase );
		}
	}

	private function packageViews(): string
	{
		return realpath( __DIR__ . '/../../../../resources/views' );
	}
}
