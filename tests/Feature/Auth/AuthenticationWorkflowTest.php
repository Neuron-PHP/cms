<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Models\User;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;

/**
 * Feature test for complete authentication workflows
 *
 * Tests end-to-end authentication scenarios including:
 * - Successful login/logout
 * - Failed login attempts
 * - CSRF token validation
 * - Session management
 * - Password verification
 */
class AuthenticationWorkflowTest extends TestCase
{
	private Authentication $auth;
	private CsrfToken $csrfToken;
	private SessionManager $sessionManager;
	private DatabaseUserRepository $userRepository;
	private SettingManager $settings;

	protected function setUp(): void
	{
		// Clear session array for test isolation
		$_SESSION = [];

		// Create test settings with memory source
		$source = new Memory();
		$source->set( 'session', 'lifetime', 3600 );
		$source->set( 'database', 'driver', 'sqlite' );
		$source->set( 'database', 'database', ':memory:' );
		$this->settings = new SettingManager( $source );

		// Initialize components
		$this->sessionManager = new SessionManager([
			'lifetime' => 3600,
			'test_mode' => true  // Prevents actual session_start()
		]);

		$this->userRepository = $this->createMock( DatabaseUserRepository::class );
		$passwordHasher = new PasswordHasher();

		$this->auth = new Authentication(
			$this->userRepository,
			$this->sessionManager,
			$passwordHasher
		);

		$this->csrfToken = new CsrfToken( $this->sessionManager );
	}

	public function test_successful_login_workflow(): void
	{
		// Create test user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( password_hash( 'password123', PASSWORD_BCRYPT ) );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setRole( User::ROLE_SUBSCRIBER );

		// Mock repository to return our test user
		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'testuser' )
			->willReturn( $user );

		// Mock resetFailedLoginAttempts (called on success)
		$this->userRepository->expects( $this->once() )
			->method( 'resetFailedLoginAttempts' )
			->with( 1 );

		// Mock findById to return refreshed user (called during login and user verification)
		$this->userRepository->expects( $this->atLeastOnce() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		// Mock update to save user
		$this->userRepository->expects( $this->once() )
			->method( 'update' )
			->with( $user );

		// Perform login
		$result = $this->auth->attempt( 'testuser', 'password123' );

		// Verify login succeeded
		$this->assertTrue( $result );
		$this->assertTrue( $this->auth->check() );
		$this->assertEquals( 1, $this->auth->id() );
		$this->assertEquals( 'testuser', $this->auth->user()->getUsername() );
		$this->assertEquals( User::ROLE_SUBSCRIBER, $this->auth->user()->getRole() );
	}

	public function test_failed_login_with_invalid_password(): void
	{
		// Create test user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( password_hash( 'password123', PASSWORD_BCRYPT ) );
		$user->setStatus( User::STATUS_ACTIVE );

		// Mock repository to return our test user
		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'testuser' )
			->willReturn( $user );

		// Mock incrementFailedLoginAttempts
		$this->userRepository->expects( $this->once() )
			->method( 'incrementFailedLoginAttempts' )
			->with( 1 )
			->willReturn( 1 );

		// Attempt login with wrong password
		$result = $this->auth->attempt( 'testuser', 'wrongpassword' );

		// Verify login failed
		$this->assertFalse( $result );
		$this->assertFalse( $this->auth->check() );
	}

	public function test_failed_login_with_nonexistent_user(): void
	{
		// Mock repository to return null (user not found)
		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'nonexistent' )
			->willReturn( null );

		// Attempt login
		$result = $this->auth->attempt( 'nonexistent', 'password123' );

		// Verify login failed
		$this->assertFalse( $result );
		$this->assertFalse( $this->auth->check() );
	}

	public function test_failed_login_with_inactive_user(): void
	{
		// Create inactive user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( password_hash( 'password123', PASSWORD_BCRYPT ) );
		$user->setStatus( User::STATUS_INACTIVE );

		// Mock repository to return inactive user
		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'testuser' )
			->willReturn( $user );

		// Attempt login
		$result = $this->auth->attempt( 'testuser', 'password123' );

		// Verify login failed
		$this->assertFalse( $result );
		$this->assertFalse( $this->auth->check() );
	}

	public function test_logout_workflow(): void
	{
		// First log in
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( password_hash( 'password123', PASSWORD_BCRYPT ) );
		$user->setStatus( User::STATUS_ACTIVE );

		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->willReturn( $user );

		// Mock resetFailedLoginAttempts
		$this->userRepository->expects( $this->once() )
			->method( 'resetFailedLoginAttempts' );

		// Mock findById to return refreshed user
		$this->userRepository->expects( $this->atLeastOnce() )
			->method( 'findById' )
			->willReturn( $user );

		// Mock update to save user (called twice: login and logout)
		$this->userRepository->expects( $this->atLeastOnce() )
			->method( 'update' );

		$this->auth->attempt( 'testuser', 'password123' );
		$this->assertTrue( $this->auth->check() );

		// Now log out
		$this->auth->logout();

		// Verify user is logged out
		$this->assertFalse( $this->auth->check() );
		$this->assertNull( $this->auth->id() );
		$this->assertNull( $this->auth->user() );
	}

	public function test_csrf_token_generation_and_validation(): void
	{
		// Generate a CSRF token
		$token = $this->csrfToken->generate();

		// Verify token is not empty
		$this->assertNotEmpty( $token );
		$this->assertIsString( $token );

		// Verify token validates successfully
		$this->assertTrue( $this->csrfToken->validate( $token ) );

		// Verify invalid token fails validation
		$this->assertFalse( $this->csrfToken->validate( 'invalid-token' ) );
	}

	public function test_csrf_token_single_use(): void
	{
		// Generate and use a token
		$token = $this->csrfToken->generate();
		$this->assertTrue( $this->csrfToken->validate( $token ) );

		// Try to use the same token again - should fail (single use)
		$this->assertFalse( $this->csrfToken->validate( $token ) );
	}

	public function test_authentication_persists_across_requests(): void
	{
		// First request: login
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( password_hash( 'password123', PASSWORD_BCRYPT ) );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setRole( User::ROLE_ADMIN );

		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->willReturn( $user );

		// Mock resetFailedLoginAttempts
		$this->userRepository->expects( $this->once() )
			->method( 'resetFailedLoginAttempts' );

		// Mock findById to return refreshed user (called for both auth instances)
		$this->userRepository->expects( $this->atLeastOnce() )
			->method( 'findById' )
			->willReturn( $user );

		// Mock update to save user
		$this->userRepository->expects( $this->once() )
			->method( 'update' );

		$this->auth->attempt( 'testuser', 'password123' );
		$this->assertTrue( $this->auth->check() );

		// Simulate second request by creating new Authentication instance
		// with same session manager (simulating persistent session)
		$auth2 = new Authentication(
			$this->userRepository,
			$this->sessionManager,
			new PasswordHasher()
		);

		// Verify authentication persists
		$this->assertTrue( $auth2->check() );
		$this->assertEquals( 1, $auth2->id() );
		$this->assertEquals( 'testuser', $auth2->user()->getUsername() );
		$this->assertEquals( User::ROLE_ADMIN, $auth2->user()->getRole() );
	}

	public function test_role_based_access_control(): void
	{
		// Create admin user
		$adminUser = new User();
		$adminUser->setId( 1 );
		$adminUser->setUsername( 'admin' );
		$adminUser->setPasswordHash( password_hash( 'admin123', PASSWORD_BCRYPT ) );
		$adminUser->setStatus( User::STATUS_ACTIVE );
		$adminUser->setRole( User::ROLE_ADMIN );

		$this->userRepository->expects( $this->once() )
			->method( 'findByUsername' )
			->with( 'admin' )
			->willReturn( $adminUser );

		// Mock resetFailedLoginAttempts
		$this->userRepository->expects( $this->once() )
			->method( 'resetFailedLoginAttempts' );

		// Mock findById to return refreshed user
		$this->userRepository->expects( $this->atLeastOnce() )
			->method( 'findById' )
			->willReturn( $adminUser );

		// Mock update to save user
		$this->userRepository->expects( $this->once() )
			->method( 'update' );

		// Login as admin
		$this->auth->attempt( 'admin', 'admin123' );

		// Verify admin has admin role
		$this->assertEquals( User::ROLE_ADMIN, $this->auth->user()->getRole() );
		$this->assertTrue( $this->auth->hasRole( User::ROLE_ADMIN ) );
	}

	public function test_session_data_management(): void
	{
		// Set various session data
		$this->sessionManager->set( 'test_key', 'test_value' );
		$this->sessionManager->set( 'user_preferences', ['theme' => 'dark', 'lang' => 'en'] );

		// Retrieve and verify
		$this->assertEquals( 'test_value', $this->sessionManager->get( 'test_key' ) );
		$this->assertEquals(
			['theme' => 'dark', 'lang' => 'en'],
			$this->sessionManager->get( 'user_preferences' )
		);

		// Test default value for non-existent key
		$this->assertEquals( 'default', $this->sessionManager->get( 'nonexistent', 'default' ) );

		// Test has()
		$this->assertTrue( $this->sessionManager->has( 'test_key' ) );
		$this->assertFalse( $this->sessionManager->has( 'nonexistent' ) );

		// Test remove()
		$this->sessionManager->remove( 'test_key' );
		$this->assertFalse( $this->sessionManager->has( 'test_key' ) );
	}
}
