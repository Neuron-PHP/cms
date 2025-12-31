<?php

namespace Tests\Cms\Services;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\PasswordResetter;
use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IPasswordResetTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

class PasswordResetterTest extends TestCase
{
	private IPasswordResetTokenRepository $_tokenRepository;
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private SettingManager $_settings;
	private string $_basePath;
	private string $_resetUrl;
	private PasswordResetter $_manager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock repositories
		$this->_tokenRepository = $this->createMock( IPasswordResetTokenRepository::class );
		$this->_userRepository = $this->createMock( IUserRepository::class );
		$this->_passwordHasher = new PasswordHasher();

		// Set up settings with Memory source wrapped in SettingManager
		$memorySource = new Memory();
		$memorySource->set( 'site', 'name', 'Test Site' );
		$memorySource->set( 'site', 'url', 'http://test.com' );
		$memorySource->set( 'email', 'from_address', 'test@example.com' );
		$memorySource->set( 'email', 'from_name', 'Test Site' );
		$memorySource->set( 'email', 'test_mode', true ); // Enable test mode to prevent actual email sending
		$this->_settings = new SettingManager( $memorySource );

		$this->_basePath = __DIR__ . '/../../../..';
		$this->_resetUrl = 'http://test.com/reset-password';

		// Create manager
		$this->_manager = new PasswordResetter(
			$this->_tokenRepository,
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_settings,
			$this->_basePath,
			$this->_resetUrl
		);
	}

	public function testConstructorSetsProperties(): void
	{
		$this->assertInstanceOf( PasswordResetter::class, $this->_manager );
	}

	public function testSetTokenExpirationMinutes(): void
	{
		$result = $this->_manager->setTokenExpirationMinutes( 120 );
		$this->assertInstanceOf( PasswordResetter::class, $result );
	}

	public function testRequestResetWithNonexistentUser(): void
	{
		$email = 'nonexistent@example.com';

		// User repository returns null
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( null );

		// Should still return true (don't reveal if user exists)
		$result = $this->_manager->requestReset( $email );

		$this->assertTrue( $result );
	}

	public function testRequestResetWithExistingUser(): void
	{
		$email = 'user@example.com';

		// Create mock user
		$user = new User();
		$user->setEmail( $email );

		// User repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( $user );

		// Expect old tokens to be deleted
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByEmail' )
			->with( $email );

		// Expect new token to be created
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->isInstanceOf( PasswordResetToken::class ) );

		// Request reset (in test mode, email won't actually be sent)
		$result = $this->_manager->requestReset( $email );

		$this->assertTrue( $result );
	}

	public function testValidateTokenWithValidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Create mock token
		$token = new PasswordResetToken( 'user@example.com', $hashedToken, 60 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		$result = $this->_manager->validateToken( $plainToken );

		$this->assertInstanceOf( PasswordResetToken::class, $result );
		$this->assertEquals( 'user@example.com', $result->getEmail() );
	}

	public function testValidateTokenWithInvalidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Repository doesn't find token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( null );

		$result = $this->_manager->validateToken( $plainToken );

		$this->assertNull( $result );
	}

	public function testValidateTokenWithExpiredToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Create expired token (negative expiration)
		$token = new PasswordResetToken( 'user@example.com', $hashedToken, -10 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		$result = $this->_manager->validateToken( $plainToken );

		$this->assertNull( $result );
	}

	public function testResetPasswordWithValidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$email = 'user@example.com';
		$newPassword = 'NewPassword123';

		// Create mock token
		$token = new PasswordResetToken( $email, $hashedToken, 60 );

		// Create mock user
		$user = new User();
		$user->setEmail( $email );
		$user->setPasswordHash( 'old-hash' );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		// Repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( $user );

		// User repository updates user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( $updatedUser ) use ( $newPassword ) {
				// Verify password was hashed and set
				return password_verify( $newPassword, $updatedUser->getPasswordHash() );
			} ) );

		// Token is deleted after use
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByToken' )
			->with( $hashedToken );

		$result = $this->_manager->resetPassword( $plainToken, $newPassword );

		$this->assertTrue( $result );
	}

	public function testResetPasswordWithInvalidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Repository doesn't find token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( null );

		$result = $this->_manager->resetPassword( $plainToken, 'NewPassword123' );

		$this->assertFalse( $result );
	}

	public function testResetPasswordWithWeakPassword(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$email = 'user@example.com';

		// Create mock token
		$token = new PasswordResetToken( $email, $hashedToken, 60 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		$this->expectException( \Exception::class );

		// Try with weak password
		$this->_manager->resetPassword( $plainToken, 'weak' );
	}

	public function testCleanupExpiredTokens(): void
	{
		// Token repository deletes expired tokens
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteExpired' )
			->willReturn( 5 );

		$result = $this->_manager->cleanupExpiredTokens();

		$this->assertEquals( 5, $result );
	}

	public function testResetPasswordWhenUserNotFound(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$email = 'deleted@example.com';

		// Create mock token
		$token = new PasswordResetToken( $email, $hashedToken, 60 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		// User repository doesn't find user (user was deleted)
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( null );

		$result = $this->_manager->resetPassword( $plainToken, 'NewPassword123!' );

		$this->assertFalse( $result );
	}

	public function testConstructorWithCustomRandom(): void
	{
		$mockRandom = $this->createMock( \Neuron\Core\System\IRandom::class );

		$mockRandom
			->expects( $this->once() )
			->method( 'string' )
			->with( 64, 'hex' )
			->willReturn( str_repeat( 'a', 64 ) );

		$user = new User();
		$user->setEmail( 'test@example.com' );

		$this->_userRepository
			->method( 'findByEmail' )
			->willReturn( $user );

		$this->_tokenRepository
			->method( 'deleteByEmail' );

		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->isInstanceOf( PasswordResetToken::class ) );

		$manager = new PasswordResetter(
			$this->_tokenRepository,
			$this->_userRepository,
			$this->_passwordHasher,
			$this->_settings,
			$this->_basePath,
			$this->_resetUrl,
			$mockRandom
		);

		$manager->requestReset( 'test@example.com' );
	}
}
