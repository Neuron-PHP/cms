<?php

namespace Tests\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Auth\Login;
use Neuron\Cms\Services\Auth\IAuthenticationService;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Neuron\Dto\Dto;
use Neuron\Mvc\IMvcApplication;
use Neuron\Routing\Router;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase
{
	private Login $controller;
	private IAuthenticationService $mockAuth;
	private IMvcApplication $mockApp;
	private SessionManager $mockSession;
	private IContainer $mockContainer;
	private Request $mockRequest;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mocks
		$this->mockAuth = $this->createMock( IAuthenticationService::class );
		$this->mockApp = $this->createMock( IMvcApplication::class );
		$this->mockSession = $this->createMock( SessionManager::class );
		$this->mockContainer = $this->createMock( IContainer::class );
		$this->mockRequest = $this->createMock( Request::class );

		// Setup mock settings for Registry
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturn( 'Test Site' );
		Registry::getInstance()->set( 'Settings', $mockSettings );

		// Setup container to return mocks
		$this->mockContainer
			->method( 'get' )
			->willReturnCallback( function( $class ) {
				if( $class === IAuthenticationService::class ) return $this->mockAuth;
				if( $class === SessionManager::class ) return $this->mockSession;
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );

		// Create controller with dependency injection
		$this->controller = new Login( $this->mockApp, $this->mockAuth, $mockSettings, $this->mockSession );
	}

	public function testConstructorWithDependencies(): void
	{
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Login( $this->mockApp, $this->mockAuth, $mockSettingManager, $mockSessionManager );
		$this->assertInstanceOf( Login::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutAuthenticationService(): void
	{
		$this->expectException( \TypeError::class );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		new Login( $this->mockApp, null, $mockSettingManager, $mockSessionManager );
	}

	public function testIsValidRedirectUrlAcceptsValidRelativeUrls(): void
	{
		// Use reflection to test private method
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $this->controller, '/dashboard' ) );
		$this->assertTrue( $method->invoke( $this->controller, '/admin/users' ) );
		$this->assertTrue( $method->invoke( $this->controller, '/path/to/page' ) );
	}

	public function testIsValidRedirectUrlRejectsEmptyUrls(): void
	{
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->controller, '' ) );
	}

	public function testIsValidRedirectUrlRejectsAbsoluteUrls(): void
	{
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->controller, 'https://evil.com' ) );
		$this->assertFalse( $method->invoke( $this->controller, 'http://evil.com' ) );
	}

	public function testIsValidRedirectUrlRejectsProtocolRelativeUrls(): void
	{
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->controller, '//evil.com' ) );
		$this->assertFalse( $method->invoke( $this->controller, '//evil.com/path' ) );
	}

	public function testIsValidRedirectUrlRejectsMaliciousPatterns(): void
	{
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		// Reject URLs with @ symbol (phishing protection)
		$this->assertFalse( $method->invoke( $this->controller, '/path@evil.com' ) );
		$this->assertFalse( $method->invoke( $this->controller, '/@evil.com' ) );

		// Reject URLs with backslashes (filter bypass protection)
		$this->assertFalse( $method->invoke( $this->controller, '/\\evil.com' ) );
		$this->assertFalse( $method->invoke( $this->controller, '/path\\evil' ) );
	}

	public function testIsValidRedirectUrlRejectsRelativeUrlsNotStartingWithSlash(): void
	{
		$reflection = new \ReflectionClass( $this->controller );
		$method = $reflection->getMethod( 'isValidRedirectUrl' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->controller, 'dashboard' ) );
		$this->assertFalse( $method->invoke( $this->controller, 'admin/users' ) );
	}
}
