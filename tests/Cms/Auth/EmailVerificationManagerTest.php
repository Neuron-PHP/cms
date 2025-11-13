<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Auth\EmailVerificationManager;
use Neuron\Cms\Models\EmailVerificationToken;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IEmailVerificationTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Data\Setting\Source\Memory;
use Neuron\Data\Setting\SettingManager;
use PHPUnit\Framework\TestCase;

class EmailVerificationManagerTest extends TestCase
{
	private IEmailVerificationTokenRepository $_tokenRepository;
	private IUserRepository $_userRepository;
	private SettingManager $_settings;
	private string $_basePath;
	private string $_verificationUrl;
	private EmailVerificationManager $_manager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock repositories
		$this->_tokenRepository = $this->createMock( IEmailVerificationTokenRepository::class );
		$this->_userRepository = $this->createMock( IUserRepository::class );

		// Set up settings with Memory source wrapped in SettingManager
		$memorySource = new Memory();
		$memorySource->set( 'site', 'name', 'Test Site' );
		$memorySource->set( 'site', 'url', 'http://test.com' );
		$memorySource->set( 'email', 'from_address', 'test@example.com' );
		$memorySource->set( 'email', 'from_name', 'Test Site' );
		$memorySource->set( 'email', 'test_mode', true ); // Enable test mode to prevent actual email sending
		$this->_settings = new SettingManager( $memorySource );

		$this->_basePath = __DIR__ . '/../../..';
		$this->_verificationUrl = 'http://test.com/verify-email';

		// Create manager
		$this->_manager = new EmailVerificationManager(
			$this->_tokenRepository,
			$this->_userRepository,
			$this->_settings,
			$this->_basePath,
			$this->_verificationUrl
		);
	}

	public function testConstructorSetsProperties(): void
	{
		$this->assertInstanceOf( EmailVerificationManager::class, $this->_manager );
	}

	public function testSetTokenExpirationMinutes(): void
	{
		$result = $this->_manager->setTokenExpirationMinutes( 120 );
		$this->assertInstanceOf( EmailVerificationManager::class, $result );
	}

	public function testSendVerificationEmail(): void
	{
		// Create mock user
		$user = new User();
		$user->setId( 1 );
		$user->setEmail( 'user@example.com' );
		$user->setUsername( 'testuser' );

		// Expect old tokens to be deleted
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByUserId' )
			->with( $user->getId() );

		// Expect new token to be created
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->isInstanceOf( EmailVerificationToken::class ) );

		// Send verification email (in test mode, email won't actually be sent)
		$result = $this->_manager->sendVerificationEmail( $user );

		$this->assertTrue( $result );
	}

	public function testValidateTokenWithValidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Create mock token
		$token = new EmailVerificationToken( 123, $hashedToken, 60 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		$result = $this->_manager->validateToken( $plainToken );

		$this->assertInstanceOf( EmailVerificationToken::class, $result );
		$this->assertEquals( 123, $result->getUserId() );
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
		$token = new EmailVerificationToken( 123, $hashedToken, -10 );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		$result = $this->_manager->validateToken( $plainToken );

		$this->assertNull( $result );
	}

	public function testVerifyEmailWithValidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$userId = 123;

		// Create mock token
		$token = new EmailVerificationToken( $userId, $hashedToken, 60 );

		// Create mock user
		$user = new User();
		$user->setId( $userId );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'user@example.com' );
		$user->setEmailVerified( false );
		$user->setStatus( User::STATUS_INACTIVE );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		// Repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( $userId )
			->willReturn( $user );

		// User repository updates user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( $updatedUser ) {
				return $updatedUser->isEmailVerified() === true &&
				       $updatedUser->getStatus() === User::STATUS_ACTIVE;
			} ) );

		// Token is deleted after use
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByToken' )
			->with( $hashedToken );

		$result = $this->_manager->verifyEmail( $plainToken );

		$this->assertTrue( $result );
	}

	public function testVerifyEmailWithInvalidToken(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Repository doesn't find token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( null );

		$result = $this->_manager->verifyEmail( $plainToken );

		$this->assertFalse( $result );
	}

	public function testVerifyEmailAlreadyVerified(): void
	{
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$userId = 123;

		// Create mock token
		$token = new EmailVerificationToken( $userId, $hashedToken, 60 );

		// Create mock user that's already verified
		$user = new User();
		$user->setId( $userId );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'user@example.com' );
		$user->setEmailVerified( true );

		// Repository finds token
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'findByToken' )
			->with( $hashedToken )
			->willReturn( $token );

		// Repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( $userId )
			->willReturn( $user );

		// Token should still be deleted
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByToken' )
			->with( $hashedToken );

		$result = $this->_manager->verifyEmail( $plainToken );

		$this->assertTrue( $result );
	}

	public function testResendVerificationWithNonexistentUser(): void
	{
		$email = 'nonexistent@example.com';

		// User repository returns null
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( null );

		// Should still return true (don't reveal if user exists)
		$result = $this->_manager->resendVerification( $email );

		$this->assertTrue( $result );
	}

	public function testResendVerificationWithAlreadyVerified(): void
	{
		$email = 'verified@example.com';

		// Create already verified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'verifieduser' );
		$user->setEmail( $email );
		$user->setEmailVerified( true );

		// User repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( $user );

		// Should return false (already verified)
		$result = $this->_manager->resendVerification( $email );

		$this->assertFalse( $result );
	}

	public function testResendVerificationWithUnverifiedUser(): void
	{
		$email = 'unverified@example.com';

		// Create unverified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'unverifieduser' );
		$user->setEmail( $email );
		$user->setEmailVerified( false );

		// User repository finds user
		$this->_userRepository
			->expects( $this->once() )
			->method( 'findByEmail' )
			->with( $email )
			->willReturn( $user );

		// Expect old tokens to be deleted
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'deleteByUserId' )
			->with( $user->getId() );

		// Expect new token to be created
		$this->_tokenRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->isInstanceOf( EmailVerificationToken::class ) );

		$result = $this->_manager->resendVerification( $email );

		$this->assertTrue( $result );
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
}
