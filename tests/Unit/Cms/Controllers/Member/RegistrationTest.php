<?php

namespace Tests\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Member\Registration;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Cms\Services\Auth\IEmailVerifier;
use Neuron\Cms\Services\Security\ResendVerificationThrottle;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use Neuron\Routing\IIpResolver;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
	private Registration $controller;
	private IRegistrationService $mockRegistrationService;
	private IEmailVerifier $mockEmailVerifier;
	private ResendVerificationThrottle $mockResendThrottle;
	private IIpResolver $mockIpResolver;
	private Application $mockApp;
	private SessionManager $mockSession;
	private SettingManager $mockSettings;
	private IContainer $mockContainer;

	protected function setUp(): void
	{
		parent::setUp();

		// Reset ViewDataProvider singleton for test isolation
		$reflection = new \ReflectionClass(\Neuron\Mvc\Views\ViewDataProvider::class);
		$instance = $reflection->getProperty('_instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);

		// Create mocks
		$this->mockRegistrationService = $this->createMock( IRegistrationService::class );
		$this->mockEmailVerifier = $this->createMock( IEmailVerifier::class );
		$this->mockResendThrottle = $this->createMock( ResendVerificationThrottle::class );
		$this->mockIpResolver = $this->createMock( IIpResolver::class );
		$this->mockApp = $this->createMock( Application::class );
		$this->mockSession = $this->createMock( SessionManager::class );
		$this->mockSettings = $this->createMock( SettingManager::class );
		$this->mockContainer = $this->createMock( IContainer::class );

		// Setup default mock settings
		$this->mockSettings->method( 'get' )->willReturnCallback( function( $section, $key = null ) {
			if( $section === 'site' && $key === 'name' ) return 'Test Site';
			if( $section === 'site' && $key === 'title' ) return 'Test Site';
			if( $section === 'site' && $key === 'description' ) return 'Test Description';
			if( $section === 'site' && $key === 'url' ) return 'https://test.com';
			if( $section === 'site' && $key === 'theme' ) return 'flatly';
			if( $section === 'views' && $key === 'path' ) return __DIR__ . '/../../../../../resources/views';
			if( $section === 'cache' && $key === 'enabled' ) return false;
			if( $section === 'member' && $key === 'require_email_verification' ) return true;
			return null;
		});

		Registry::getInstance()->set( 'Settings', $this->mockSettings );
		Registry::getInstance()->set( 'Views.Path', __DIR__ . '/../../../../../resources/views' );

		// Setup ViewDataProvider for global view variables
		$provider = \Neuron\Mvc\Views\ViewDataProvider::getInstance();
		$provider->share( 'siteName', 'Test Site' );
		$provider->share( 'appVersion', '1.0.0-test' );
		$provider->share( 'theme', 'flatly' );
		$provider->share( 'currentYear', fn() => date('Y') );
		$provider->share( 'isAuthenticated', false );
		$provider->share( 'currentUser', null );

		// Setup container to return mocks
		$this->mockContainer
			->method( 'get' )
			->willReturnCallback( function( $class ) {
				if( $class === IRegistrationService::class ) return $this->mockRegistrationService;
				if( $class === IEmailVerifier::class ) return $this->mockEmailVerifier;
				if( $class === ResendVerificationThrottle::class ) return $this->mockResendThrottle;
				if( $class === IIpResolver::class ) return $this->mockIpResolver;
				if( $class === SessionManager::class ) return $this->mockSession;
				if( $class === SettingManager::class ) return $this->mockSettings;
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );

		// Create controller with all dependencies
		$this->controller = new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			$this->mockRegistrationService,
			$this->mockEmailVerifier,
			$this->mockResendThrottle,
			$this->mockIpResolver
		);
	}

	protected function tearDown(): void
	{
		// Clean up Registry state
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testConstructorWithAllDependencies(): void
	{
		$controller = new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			$this->mockRegistrationService,
			$this->mockEmailVerifier,
			$this->mockResendThrottle,
			$this->mockIpResolver
		);

		$this->assertInstanceOf( Registration::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutRegistrationService(): void
	{
		$this->expectException( \InvalidArgumentException::class );

		new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			null,
			$this->mockEmailVerifier,
			$this->mockResendThrottle,
			$this->mockIpResolver
		);
	}

	public function testConstructorThrowsExceptionWithoutEmailVerifier(): void
	{
		$this->expectException( \InvalidArgumentException::class );

		new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			$this->mockRegistrationService,
			null,
			$this->mockResendThrottle,
			$this->mockIpResolver
		);
	}

	public function testConstructorThrowsExceptionWithoutResendThrottle(): void
	{
		$this->expectException( \InvalidArgumentException::class );

		new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			$this->mockRegistrationService,
			$this->mockEmailVerifier,
			null,
			$this->mockIpResolver
		);
	}

	public function testConstructorThrowsExceptionWithoutIpResolver(): void
	{
		$this->expectException( \InvalidArgumentException::class );

		new Registration(
			$this->mockApp,
			$this->mockSettings,
			$this->mockSession,
			$this->mockRegistrationService,
			$this->mockEmailVerifier,
			$this->mockResendThrottle,
			null
		);
	}

	public function testShowRegistrationFormWhenEnabled(): void
	{
		$request = $this->createMock( Request::class );

		$this->mockRegistrationService
			->method( 'isRegistrationEnabled' )
			->willReturn( true );

		$this->mockSession->method( 'getFlash' )->willReturn( null );

		$result = $this->controller->showRegistrationForm( $request );

		$this->assertIsString( $result );
	}

	public function testShowRegistrationFormWhenDisabled(): void
	{
		$request = $this->createMock( Request::class );

		$this->mockRegistrationService
			->method( 'isRegistrationEnabled' )
			->willReturn( false );

		$result = $this->controller->showRegistrationForm( $request );

		$this->assertIsString( $result );
	}

	public function testShowVerificationSent(): void
	{
		$request = $this->createMock( Request::class );

		$this->mockSession->method( 'getFlash' )->willReturn( 'Verification email sent' );

		$result = $this->controller->showVerificationSent( $request );

		$this->assertIsString( $result );
	}

	public function testVerifyWithValidToken(): void
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturn( 'valid-token-123' );

		$this->mockEmailVerifier
			->method( 'verifyEmail' )
			->with( 'valid-token-123' )
			->willReturn( true );

		$result = $this->controller->verify( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'verified successfully', $result );
	}

	public function testVerifyWithEmptyToken(): void
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturn( '' );

		$result = $this->controller->verify( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Invalid or missing verification token', $result );
	}

	public function testVerifyWithInvalidToken(): void
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturn( 'invalid-token' );

		$this->mockEmailVerifier
			->method( 'verifyEmail' )
			->with( 'invalid-token' )
			->willReturn( false );

		$result = $this->controller->verify( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'invalid or has expired', $result );
	}

	public function testVerifyWithException(): void
	{
		$request = $this->createMock( Request::class );
		$request->method( 'get' )->willReturn( 'token-causing-error' );

		$this->mockEmailVerifier
			->method( 'verifyEmail' )
			->willThrowException( new \Exception( 'Database error' ) );

		$result = $this->controller->verify( $request );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'An error occurred', $result );
	}
}
