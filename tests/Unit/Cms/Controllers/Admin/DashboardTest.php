<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\Dashboard;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Views\ViewContext;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class DashboardTest extends TestCase
{
	private SettingManager $_settingManager;
	private $mockSettingManager;
	private $mockSessionManager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( 'Settings', $this->_settingManager );

		// Create version file
		$versionContent = json_encode([ 'major' => 1, 'minor' => 0, 'patch' => 0 ]);
		$parentDir = dirname( getcwd() );
		if( !file_exists( $parentDir . '/.version.json' ) )
		{
			file_put_contents( $parentDir . '/.version.json', $versionContent );
		}

		$this->mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$this->mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );
		Registry::getInstance()->set( 'CsrfToken', null );

		$parentDir = dirname( getcwd() );
		@unlink( $parentDir . '/.version.json' );

		parent::tearDown();
	}

	public function testConstructor(): void
	{
		$controller = new Dashboard( null, $this->mockSettingManager, $this->mockSessionManager );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	public function testConstructorWithApplication(): void
	{
		$mockApp = $this->createMock( Application::class );
		$controller = new Dashboard( $mockApp, $this->mockSettingManager, $this->mockSessionManager );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testIndexRendersView(): void
	{
		// Mock the controller to test view() method chain
		$controller = $this->getMockBuilder( Dashboard::class )
			->setConstructorArgs( [ null, $this->mockSettingManager, $this->mockSessionManager ] )
			->onlyMethods( [ 'view' ] )
			->getMock();

		// Create a mock ViewContext that supports the fluent interface
		$mockViewContext = $this->getMockBuilder( ViewContext::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'title', 'description', 'withCurrentUser', 'withCsrfToken', 'render' ] )
			->getMock();

		$mockViewContext->method( 'title' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'description' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCurrentUser' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'withCsrfToken' )->willReturn( $mockViewContext );
		$mockViewContext->method( 'render' )->willReturn( '<html>Dashboard</html>' );

		$controller->method( 'view' )->willReturn( $mockViewContext );

		$request = new Request();
		$result = $controller->index( $request );

		$this->assertEquals( '<html>Dashboard</html>', $result );
	}
}
