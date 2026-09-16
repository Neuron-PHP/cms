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
}
