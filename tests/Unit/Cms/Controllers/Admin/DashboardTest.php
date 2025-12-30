<?php

namespace Tests\Unit\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Dashboard;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class DashboardTest extends TestCase
{
	private Application $mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		// Setup mock settings
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturnCallback( function( $section, $key = null ) {
			if( $section === 'views' && $key === 'path' ) return '/tmp/views';
			if( $section === 'cache' && $key === 'enabled' ) return false;
			return 'Test Site';
		});
		Registry::getInstance()->set( 'Settings', $mockSettings );

		// Create mock application
		$this->mockApp = $this->createMock( Application::class );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testConstructorWithApplication(): void
	{
		$controller = new Dashboard( $this->mockApp );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	public function testConstructorWithNullApplication(): void
	{
		$controller = new Dashboard( null );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	public function testConstructorExtendsContentController(): void
	{
		$controller = new Dashboard( $this->mockApp );
		$this->assertInstanceOf( \Neuron\Cms\Controllers\Content::class, $controller );
	}
}
