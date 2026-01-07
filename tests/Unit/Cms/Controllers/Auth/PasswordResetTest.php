<?php

namespace Tests\Cms\Controllers\Auth;

use Neuron\Cms\Controllers\Auth\PasswordReset;
use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Cms\Services\Auth\IPasswordResetter;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Dto\Dto;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class PasswordResetTest extends TestCase
{
	private PasswordReset $controller;
	private IPasswordResetter $mockPasswordResetter;
	private Application $mockApp;
	private SessionManager $mockSession;
	private IContainer $mockContainer;
	private Request $mockRequest;

	protected function setUp(): void
	{
		parent::setUp();

		// Reset ViewDataProvider singleton for test isolation
		$reflection = new \ReflectionClass(\Neuron\Mvc\Views\ViewDataProvider::class);
		$instance = $reflection->getProperty('_instance');
		$instance->setAccessible(true);
		$instance->setValue(null, null);

		// Create mocks
		$this->mockPasswordResetter = $this->createMock( IPasswordResetter::class );
		$this->mockApp = $this->createMock( Application::class );
		$this->mockSession = $this->createMock( SessionManager::class );
		$this->mockContainer = $this->createMock( IContainer::class );
		$this->mockRequest = $this->createMock( Request::class );

		// Setup mock settings for Registry
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturnCallback( function( $section, $key = null ) {
			if( $section === 'views' && $key === 'path' ) return __DIR__ . '/../../../../../resources/views';
			if( $section === 'cache' && $key === 'enabled' ) return false;
			return 'Test Site';
		});

		// Create a mock setting source for the SettingManager
		$mockSettingSource = $this->createMock( \Neuron\Data\Settings\Source\ISettingSource::class );
		$mockSettingSource->method( 'get' )->willReturn( false ); // Cache disabled

		// IMPORTANT: Mock the getSource() method to return the mock setting source
		$mockSettings->method( 'getSource' )->willReturn( $mockSettingSource );

		Registry::getInstance()->set( 'Settings', $mockSettings );
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
				if( $class === IPasswordResetter::class ) return $this->mockPasswordResetter;
				if( $class === SessionManager::class ) return $this->mockSession;
				if( $class === DtoFactoryService::class ) {
					$dtoFactory = $this->createMock( DtoFactoryService::class );
					// Create a valid DTO for testing
					$dto = new class extends Dto {
						public string $email = 'test@example.com';
						public string $token = 'test-token-123';
						public string $password = 'NewPassword123!';
						public string $password_confirmation = 'NewPassword123!';

						public function getRules(): array {
							return [];
						}
					};
					$dtoFactory->method( 'create' )->willReturn( $dto );
					return $dtoFactory;
				}
				return null;
			});

		$this->mockApp
			->method( 'getContainer' )
			->willReturn( $this->mockContainer );

		// Create controller with dependency injection
		$this->controller = new PasswordReset( $this->mockApp, $mockSettings, $this->mockSession, $this->mockPasswordResetter );
	}

	protected function tearDown(): void
	{
		// Clean up Registry state
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testConstructorWithDependencies(): void
	{
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new PasswordReset( $this->mockApp, $mockSettingManager, $mockSessionManager, $this->mockPasswordResetter );
		$this->assertInstanceOf( PasswordReset::class, $controller );
	}

	public function testConstructorThrowsExceptionWithoutPasswordResetter(): void
	{
		$this->expectException( \TypeError::class );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		new PasswordReset( $this->mockApp, $mockSettingManager, $mockSessionManager, null );
	}

	public function testShowForgotPasswordFormReturnsView(): void
	{
		$this->mockSession->method( 'getFlash' )->willReturn( null );

		$result = $this->controller->showForgotPasswordForm( $this->mockRequest );

		$this->assertIsString( $result );
	}

	public function testShowResetFormWithValidToken(): void
	{
		$this->mockRequest->method( 'get' )->willReturn( 'valid-token-123' );

		$mockToken = $this->createMock( PasswordResetToken::class );
		$mockToken->method( 'getEmail' )->willReturn( 'test@example.com' );

		$this->mockPasswordResetter
			->method( 'validateToken' )
			->with( 'valid-token-123' )
			->willReturn( $mockToken );

		$this->mockSession->method( 'getFlash' )->willReturn( null );

		$result = $this->controller->showResetForm( $this->mockRequest );

		$this->assertIsString( $result );
	}
}
