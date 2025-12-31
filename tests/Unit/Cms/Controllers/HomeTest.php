<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Controllers\Home;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class HomeTest extends TestCase
{
	private SettingManager $_settingManager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );

		// Wrap in SettingManager
		$this->_settingManager = new SettingManager( $settings );

		// Store settings in registry
		Registry::getInstance()->set( 'Settings', $this->_settingManager );

		// Create version file
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 0,
			'patch' => 0
		]);

		$parentDir = dirname( getcwd() );
		if( !file_exists( $parentDir . '/.version.json' ) )
		{
			file_put_contents( $parentDir . '/.version.json', $versionContent );
		}
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
		$parentDir = dirname( getcwd() );
		@unlink( $parentDir . '/.version.json' );

		parent::tearDown();
	}

	public function testConstructorWithRegistrationService(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Home( null, $mockRegistrationService, $mockSettingManager, $mockSessionManager );

		$this->assertInstanceOf( Home::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutDependencies(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		// Either SettingManager or IRegistrationService exception will be thrown
		// depending on check order (SettingManager is checked in parent first)

		new Home( null, null, null, null );
	}

	public function testIndexWithRegistrationEnabled(): void
	{
		$mockRegistrationService = $this->createMock( IRegistrationService::class );
		$mockRegistrationService->method( 'isRegistrationEnabled' )->willReturn( true );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		// Mock the controller to test renderHtml is called with correct params
		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ null, $mockRegistrationService, $mockSettingManager, $mockSessionManager ] )
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

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ null, $mockRegistrationService, $mockSettingManager, $mockSessionManager ] )
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

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Home::class )
			->setConstructorArgs( [ null, $mockRegistrationService, $mockSettingManager, $mockSessionManager ] )
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
