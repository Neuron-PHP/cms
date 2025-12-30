<?php

namespace Tests\Cms\Services;

use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Events\Emitter;
use PHPUnit\Framework\TestCase;

class RegistrationServiceTest extends TestCase
{
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private EmailVerifier $_emailVerifier;
	private SettingManager $_settings;
	private Emitter $_emitter;
	private RegistrationService $_service;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mocks
		$this->_userRepository = $this->createMock( IUserRepository::class );
		$this->_passwordHasher = new PasswordHasher();
		$this->_emailVerifier = $this->createMock( EmailVerifier::class );
		$this->_emitter = $this->createMock( Emitter::class );

		// Set up settings with Memory source
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', true );
		$memorySource->set( 'member', 'require_email_verification', true );
		$memorySource->set( 'member', 'default_role', User::ROLE_SUBSCRIBER );
		$this->_settings = new SettingManager( $memorySource );

		// Create service
		$this->_service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$this->_settings,
			$this->_emitter
		);
	}

	public function testConstructor(): void
	{
		$this->assertInstanceOf( RegistrationService::class, $this->_service );
	}

	public function testIsRegistrationEnabledReturnsTrue(): void
	{
		$result = $this->_service->isRegistrationEnabled();
		$this->assertTrue( $result );
	}

	public function testIsRegistrationEnabledReturnsFalse(): void
	{
		// Update settings to disable registration
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', false );
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		$result = $service->isRegistrationEnabled();
		$this->assertFalse( $result );
	}

	public function testRegisterWithValidData(): void
	{
		$username = 'newuser';
		$email = 'newuser@example.com';
		$password = 'SecurePass123';
		$passwordConfirmation = 'SecurePass123';

		// User repository checks should return null (user doesn't exist)
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByUsername' )
			->with( $username )
			->willReturn( null );

		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( null );

		// Expect user to be created
		$this->_userRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( $user ) use ( $username, $email ) {
				return $user instanceof User &&
				       $user->getUsername() === $username &&
				       $user->getEmail() === $email &&
				       $user->getRole() === User::ROLE_SUBSCRIBER &&
				       $user->getStatus() === User::STATUS_INACTIVE &&
				       !$user->isEmailVerified();
			} ) )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Expect verification email to be sent
		$this->_emailVerifier
			->expects( $this->once() )
			->method( 'sendVerificationEmail' );

		// Register user
		$user = $this->_service->register( $username, $email, $password, $passwordConfirmation );

		$this->assertInstanceOf( User::class, $user );
		$this->assertEquals( $username, $user->getUsername() );
		$this->assertEquals( $email, $user->getEmail() );
	}

	public function testRegisterWhenDisabled(): void
	{
		// Update settings to disable registration
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', false );
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'User registration is currently disabled' );

		$service->register( 'user', 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithEmptyUsername(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username is required' );

		$this->_service->register( '', 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithShortUsername(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username must be between 3 and 50 characters' );

		$this->_service->register( 'ab', 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithLongUsername(): void
	{
		$longUsername = str_repeat( 'a', 51 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username must be between 3 and 50 characters' );

		$this->_service->register( $longUsername, 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithInvalidUsernameCharacters(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username can only contain letters, numbers, and underscores' );

		$this->_service->register( 'user@name', 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithExistingUsername(): void
	{
		$username = 'existinguser';

		// User repository finds existing user
		$existingUser = new User();
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByUsername' )
			->with( $username )
			->willReturn( $existingUser );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username is already taken' );

		$this->_service->register( $username, 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithInvalidEmail(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid email address' );

		$this->_service->register( 'user', 'invalid-email', 'Password123', 'Password123' );
	}

	public function testRegisterWithExistingEmail(): void
	{
		$email = 'existing@example.com';

		// Username check passes
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByUsername' )
			->willReturn( null );

		// Email check finds existing user
		$existingUser = new User();
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( $existingUser );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Email is already registered' );

		$this->_service->register( 'user', $email, 'Password123', 'Password123' );
	}

	public function testRegisterWithPasswordMismatch(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Passwords do not match' );

		$this->_service->register( 'user', 'user@example.com', 'Password123', 'Different123' );
	}

	public function testRegisterWithWeakPassword(): void
	{
		$this->expectException( \Exception::class );

		$this->_service->register( 'user', 'user@example.com', 'weak', 'weak' );
	}

	public function testRegisterWithoutEmailVerification(): void
	{
		// Update settings to not require email verification
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', true );
		$memorySource->set( 'member', 'require_email_verification', false );
		$memorySource->set( 'member', 'default_role', User::ROLE_SUBSCRIBER );
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		// Username and email checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );

		// Expect user to be created as active
		$this->_userRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( $user ) {
				return $user instanceof User &&
				       $user->getStatus() === User::STATUS_ACTIVE &&
				       $user->isEmailVerified();
			} ) )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Verification email should NOT be sent
		$this->_emailVerifier
			->expects( $this->never() )
			->method( 'sendVerificationEmail' );

		$user = $service->register( 'user', 'user@example.com', 'Password123', 'Password123' );

		$this->assertTrue( $user->isEmailVerified() );
		$this->assertEquals( User::STATUS_ACTIVE, $user->getStatus() );
	}

	public function testRegisterWithDtoSuccess(): void
	{
		// Create DTO with properties using anonymous class
		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// User repository checks should return null (user doesn't exist)
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );

		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );

		// Expect user to be created
		$this->_userRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( $user ) {
				return $user instanceof User &&
				       $user->getUsername() === 'newuser' &&
				       $user->getEmail() === 'newuser@example.com';
			} ) )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Expect verification email to be sent
		$this->_emailVerifier
			->expects( $this->once() )
			->method( 'sendVerificationEmail' );

		// Register user with DTO
		$user = $this->_service->registerWithDto( $dto );

		$this->assertInstanceOf( User::class, $user );
		$this->assertEquals( 'newuser', $user->getUsername() );
		$this->assertEquals( 'newuser@example.com', $user->getEmail() );
	}

	public function testRegisterWithDtoWhenRegistrationDisabled(): void
	{
		// Disable registration
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', false );
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'User registration is currently disabled' );

		$service->registerWithDto( $dto );
	}

	public function testRegisterWithDtoPasswordMismatch(): void
	{
		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'DifferentPass456';

			protected function spec(): array { return []; }
		};

		// Username and email checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Passwords do not match' );

		$this->_service->registerWithDto( $dto );
	}

	public function testRegisterWithDtoExistingUsername(): void
	{
		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'existinguser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// Username exists
		$existingUser = new User();
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'existinguser' )
			->willReturn( $existingUser );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username is already taken' );

		$this->_service->registerWithDto( $dto );
	}

	public function testRegisterWithDtoExistingEmail(): void
	{
		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'existing@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// Username check passes
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );

		// Email exists
		$existingUser = new User();
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( 'existing@example.com' )
			->willReturn( $existingUser );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Email is already registered' );

		$this->_service->registerWithDto( $dto );
	}

	public function testRegisterWithDtoWithoutEmailVerification(): void
	{
		// Disable email verification
		$memorySource = new Memory();
		$memorySource->set( 'member', 'registration_enabled', true );
		$memorySource->set( 'member', 'require_email_verification', false );
		$memorySource->set( 'member', 'default_role', User::ROLE_SUBSCRIBER );
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );

		// Expect user to be created as active
		$this->_userRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( $user ) {
				return $user instanceof User &&
				       $user->getStatus() === User::STATUS_ACTIVE &&
				       $user->isEmailVerified();
			} ) )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Verification email should NOT be sent
		$this->_emailVerifier
			->expects( $this->never() )
			->method( 'sendVerificationEmail' );

		$user = $service->registerWithDto( $dto );

		$this->assertTrue( $user->isEmailVerified() );
		$this->assertEquals( User::STATUS_ACTIVE, $user->getStatus() );
	}

	public function testRegisterEmitsUserCreatedEvent(): void
	{
		// Setup with real emitter
		$emitter = $this->createMock( Emitter::class );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$this->_settings,
			$emitter
		);

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );
		$this->_userRepository
			->method( 'create' )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Expect event to be emitted
		$emitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\UserCreatedEvent::class ) );

		$service->register( 'user', 'user@example.com', 'Password123', 'Password123' );
	}

	public function testRegisterWithoutEmitter(): void
	{
		// Create service without emitter (null)
		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$this->_settings,
			null
		);

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );
		$this->_userRepository
			->method( 'create' )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Should not throw exception even without emitter
		$user = $service->register( 'user', 'user@example.com', 'Password123', 'Password123' );
		$this->assertInstanceOf( User::class, $user );
	}

	/**
	 * Test registration succeeds even when email verification fails
	 */
	public function testRegisterSucceedsWhenEmailVerificationFails(): void
	{
		$username = 'newuser';
		$email = 'newuser@example.com';
		$password = 'SecurePass123';

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );
		$this->_userRepository
			->method( 'create' )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Email verification throws exception (network error, etc.)
		$this->_emailVerifier
			->expects( $this->once() )
			->method( 'sendVerificationEmail' )
			->willThrowException( new \Exception( 'Email service unavailable' ) );

		// Registration should still succeed (user can request resend later)
		$user = $this->_service->register( $username, $email, $password, $password );

		$this->assertInstanceOf( User::class, $user );
		$this->assertEquals( $username, $user->getUsername() );
	}

	/**
	 * Test registerWithDto succeeds even when email verification fails
	 */
	public function testRegisterWithDtoSucceedsWhenEmailVerificationFails(): void
	{
		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );
		$this->_userRepository
			->method( 'create' )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Email verification throws exception
		$this->_emailVerifier
			->expects( $this->once() )
			->method( 'sendVerificationEmail' )
			->willThrowException( new \Exception( 'Email service unavailable' ) );

		// Registration should still succeed
		$user = $this->_service->registerWithDto( $dto );

		$this->assertInstanceOf( User::class, $user );
		$this->assertEquals( 'newuser', $user->getUsername() );
	}

	/**
	 * Test registration with empty email
	 */
	public function testRegisterWithEmptyEmail(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Email is required' );

		$this->_service->register( 'user', '', 'Password123', 'Password123' );
	}

	/**
	 * Test registration with empty password
	 */
	public function testRegisterWithEmptyPassword(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Password is required' );

		$this->_service->register( 'user', 'user@example.com', '', '' );
	}

	/**
	 * Test registerWithDto emits UserCreatedEvent
	 */
	public function testRegisterWithDtoEmitsUserCreatedEvent(): void
	{
		// Setup with real emitter
		$emitter = $this->createMock( Emitter::class );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$this->_settings,
			$emitter
		);

		$dto = new class extends \Neuron\Dto\Dto {
			public string $username = 'newuser';
			public string $email = 'newuser@example.com';
			public string $password = 'SecurePass123';
			public string $password_confirmation = 'SecurePass123';

			protected function spec(): array { return []; }
		};

		// Checks pass
		$this->_userRepository
			->method( 'findByUsername' )
			->willReturn( null );
		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( null );
		$this->_userRepository
			->method( 'create' )
			->willReturnCallback( function( $user ) {
				$user->setId( 1 );
				return $user;
			} );

		// Expect event to be emitted
		$emitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\UserCreatedEvent::class ) );

		$service->registerWithDto( $dto );
	}

	/**
	 * Test isRegistrationEnabled returns true by default when setting is null
	 */
	public function testIsRegistrationEnabledDefaultsToTrue(): void
	{
		// Create settings with no registration_enabled setting
		$memorySource = new Memory();
		$settings = new SettingManager( $memorySource );

		$service = new RegistrationService(
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_emailVerifier,
			$settings,
			$this->_emitter
		);

		$result = $service->isRegistrationEnabled();
		$this->assertTrue( $result, 'Registration should be enabled by default' );
	}
}
