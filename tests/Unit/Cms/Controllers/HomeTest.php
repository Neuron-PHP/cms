<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Controllers\Home;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class HomeTest extends TestCase
{
	private SettingManager $_settingManager;
	private string $_versionFilePath;
	private IMvcApplication $_mockApp;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock application
		$router = $this->createMock( Router::class );
		$this->_mockApp = $this->createMock( IMvcApplication::class );
		$this->_mockApp->method( 'getRouter' )->willReturn( $router );

		// Create version file in temp directory
		$this->_versionFilePath = sys_get_temp_dir() . '/neuron-test-version-' . uniqid() . '.json';
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 0,
			'patch' => 0
		]);
		file_put_contents( $this->_versionFilePath, $versionContent );

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		// Wrap in SettingManager
		$this->_settingManager = new SettingManager( $settings );

		// Store settings in registry
		Registry::getInstance()->set( 'Settings', $this->_settingManager );
	}

	protected function tearDown(): void
	{
		// Clear registry
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );

		// Clean up temp version file
		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		parent::tearDown();
	}

	public function testConstructorWithRegistrationService(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Home( $this->_mockApp, $this->_settingManager, $mockSessionManager, $mockRegistrationService );

		$this->assertInstanceOf( Home::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutDependencies(): void
	{
		$this->expectException( \TypeError::class );
		// IMvcApplication is required (non-nullable), so TypeError will be thrown

		new Home( $this->_mockApp, null, null, null );
	}

	public function testIndexWithRegistrationEnabled(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockRegistrationService->method( 'isRegistrationEnabled' )->willReturn( true );

		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		// Mock the controller to test renderHtml is called with correct params
		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ $this->_mockApp, $this->_settingManager, $mockSessionManager, $mockRegistrationService ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					return isset( $data['RegistrationEnabled'] ) &&
					       $data['RegistrationEnabled'] === true &&
					       isset( $data['Title'] ) &&
					       isset( $data['Name'] ) &&
					       isset( $data['Description'] );
				} ),
				'index'
			)
			->willReturn( '<html>Home Page</html>' );

		$request = new Request();
		$result = $controller->index( $request );

		$this->assertEquals( '<html>Home Page</html>', $result );
	}

	public function testIndexWithRegistrationDisabled(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockRegistrationService->method( 'isRegistrationEnabled' )->willReturn( false );

		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ $this->_mockApp, $this->_settingManager, $mockSessionManager, $mockRegistrationService ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					return isset( $data['RegistrationEnabled'] ) &&
					       $data['RegistrationEnabled'] === false;
				} ),
				'index'
			)
			->willReturn( '<html>Home Page</html>' );

		$request = new Request();
		$result = $controller->index( $request );

		$this->assertEquals( '<html>Home Page</html>', $result );
	}


	public function testIndexPassesCorrectDataToView(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockRegistrationService->method( 'isRegistrationEnabled' )->willReturn( true );

		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ $this->_mockApp, $this->_settingManager, $mockSessionManager, $mockRegistrationService ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					// Verify all required keys are present
					return isset( $data['Title'] ) &&
					       isset( $data['Name'] ) &&
					       isset( $data['Description'] ) &&
					       isset( $data['RegistrationEnabled'] ) &&
					       $data['RegistrationEnabled'] === true;
				} ),
				'index'
			)
			->willReturn( '<html>Home Page</html>' );

		$request = new Request();
		$controller->index( $request );
	}
}
