<?php

namespace Tests\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Member\Dashboard;
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
	private string $_versionFilePath;

	protected function setUp(): void
	{
		parent::setUp();

		// Create version file in temp directory
		$this->_versionFilePath = sys_get_temp_dir() . '/neuron-test-version-' . uniqid() . '.json';
		$versionContent = json_encode([ 'major' => 1, 'minor' => 0, 'patch' => 0 ]);
		file_put_contents( $this->_versionFilePath, $versionContent );

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		$this->_settingManager = new SettingManager( $settings );
		Registry::getInstance()->set( 'Settings', $this->_settingManager );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );
		Registry::getInstance()->set( 'CsrfToken', null );

		// Clean up temp version file
		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		parent::tearDown();
	}

	public function testConstructor(): void
	{
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Dashboard( null, $mockSettingManager, $mockSessionManager );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	public function testConstructorWithApplication(): void
	{
		$mockApp = $this->createMock( Application::class );
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Dashboard( $mockApp, $mockSettingManager, $mockSessionManager );
		$this->assertInstanceOf( Dashboard::class, $controller );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testIndexRendersView(): void
	{
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		// Mock the controller to test view() method chain
		$controller = $this->getMockBuilder( Dashboard::class )
			->setConstructorArgs( [ null, $mockSettingManager, $mockSessionManager ] )
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
		$mockViewContext->method( 'render' )->willReturn( '<html>Member Dashboard</html>' );

		$controller->method( 'view' )->willReturn( $mockViewContext );

		$request = new Request();
		$result = $controller->index( $request );

		$this->assertEquals( '<html>Member Dashboard</html>', $result );
	}
}
