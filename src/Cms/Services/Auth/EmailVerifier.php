<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\EmailVerificationToken;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IEmailVerificationTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Core\System\IRandom;
use Neuron\Core\System\RealRandom;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Exception;
use Neuron\Cms\Enums\UserStatus;

/**
 * Email verification service.
 *
 * Handles email verification token generation, validation, and account activation.
 *
 * @package Neuron\Cms\Services\Auth
 */
class EmailVerifier
{
	private IEmailVerificationTokenRepository $_tokenRepository;
	private IUserRepository $_userRepository;
	private SettingManager $_settings;
	private IRandom $_random;
	private string $_basePath;
	private string $_verificationUrl;
	private int $_tokenExpirationMinutes = 60;

	/**
	 * Constructor
	 *
	 * @param IEmailVerificationTokenRepository $tokenRepository Token repository
	 * @param IUserRepository $userRepository User repository
	 * @param SettingManager $settings Settings manager with email configuration
	 * @param string $basePath Base path for template loading
	 * @param string $verificationUrl Base URL for email verification (token will be appended)
	 * @param IRandom|null $random Random generator (defaults to cryptographically secure)
	 */
	public function __construct(
		IEmailVerificationTokenRepository $tokenRepository,
		IUserRepository $userRepository,
		SettingManager $settings,
		string $basePath,
		string $verificationUrl,
		?IRandom $random = null
	)
	{
		$this->_tokenRepository = $tokenRepository;
		$this->_userRepository = $userRepository;
		$this->_settings = $settings;
		$this->_basePath = $basePath;
		$this->_verificationUrl = $verificationUrl;
		$this->_random = $random ?? new RealRandom();
	}

	/**
	 * Set token expiration time in minutes
	 */
	public function setTokenExpirationMinutes( int $minutes ): self
	{
		$this->_tokenExpirationMinutes = $minutes;
		return $this;
	}

	/**
	 * Send verification email to a user
	 *
	 * Generates a secure token, stores it, and sends an email to the user.
	 *
	 * @param User $user User to send verification email to
	 * @return bool True if verification email was sent
	 * @throws Exception if email sending fails
	 */
	public function sendVerificationEmail( User $user ): bool
	{
		// Delete any existing tokens for this user
		$this->_tokenRepository->deleteByUserId( $user->getId() );

		// Generate secure random token (64 hex characters = 32 bytes)
		$plainToken = $this->_random->string( 64, 'hex' );
		$hashedToken = hash( 'sha256', $plainToken );

		// Create and store token
		$token = new EmailVerificationToken(
			$user->getId(),
			$hashedToken,
			$this->_tokenExpirationMinutes
		);

		$this->_tokenRepository->create( $token );

		// Send verification email
		$this->sendEmail( $user, $plainToken );

		return true;
	}

	/**
	 * Validate a verification token
	 *
	 * @param string $plainToken Plain text token from URL
	 * @return EmailVerificationToken|null Token if valid and not expired, null otherwise
	 */
	public function validateToken( string $plainToken ): ?EmailVerificationToken
	{
		$hashedToken = hash( 'sha256', $plainToken );

		$token = $this->_tokenRepository->findByToken( $hashedToken );

		if( !$token || $token->isExpired() )
		{
			return null;
		}

		return $token;
	}

	/**
	 * Verify email and activate user account
	 *
	 * @param string $plainToken Plain text token from URL
	 * @return bool True if email was verified, false if token invalid or expired
	 * @throws Exception if user update fails
	 */
	public function verifyEmail( string $plainToken ): bool
	{
		// Validate token
		$token = $this->validateToken( $plainToken );

		if( !$token )
		{
			return false;
		}

		// Find user
		$user = $this->_userRepository->findById( $token->getUserId() );

		if( !$user )
		{
			return false;
		}

		// Check if already verified
		if( $user->isEmailVerified() )
		{
			// Delete the token even if already verified
			$this->_tokenRepository->deleteByToken( hash( 'sha256', $plainToken ) );
			return true;
		}

		// Update user email verification status
		$user->setEmailVerified( true );

		if( $user->getStatus() === UserStatus::INACTIVE->value )
		{
			$user->setStatus( UserStatus::ACTIVE->value );
		}
		$this->_userRepository->update( $user );

		// Delete the token
		$this->_tokenRepository->deleteByToken( hash( 'sha256', $plainToken ) );

		Log::info( "Email verified for user: {$user->getUsername()}" );

		// Emit email verified event
		\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\EmailVerifiedEvent( $user ) );

		return true;
	}

	/**
	 * Resend verification email to a user by email address
	 *
	 * @param string $email User's email address
	 * @return bool True if email was sent, false if user not found or already verified
	 * @throws Exception if email sending fails
	 */
	public function resendVerification( string $email ): bool
	{
		$user = $this->_userRepository->findByEmail( $email );

		if( !$user )
		{
			// Don't reveal whether email exists in system
			return true;
		}

		// Don't send if already verified
		if( $user->isEmailVerified() )
		{
			return false;
		}

		return $this->sendVerificationEmail( $user );
	}

	/**
	 * Clean up expired tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function cleanupExpiredTokens(): int
	{
		return $this->_tokenRepository->deleteExpired();
	}

	/**
	 * Send verification email
	 *
	 * @param User $user User to send email to
	 * @param string $plainToken Plain text token to include in URL
	 * @throws Exception if email sending fails
	 */
	private function sendEmail( User $user, string $plainToken ): void
	{
		$verificationLink = $this->_verificationUrl . '?token=' . urlencode( $plainToken );

		// Get site name from settings
		$siteName = $this->_settings->get( 'site', 'name' ) ?? 'Neuron CMS';

		// Prepare template data
		$templateData = [
			'VerificationLink' => $verificationLink,
			'ExpirationMinutes' => $this->_tokenExpirationMinutes,
			'SiteName' => $siteName,
			'Username' => $user->getUsername()
		];

		try
		{
			// Send email using Sender service with template
			$sender = new Sender( $this->_settings, $this->_basePath );
			$result = $sender
				->to( $user->getEmail() )
				->subject( "Verify Your Email Address - {$siteName}" )
				->template( 'emails/email-verification', $templateData )
				->send();

			if( !$result )
			{
				throw new Exception( 'Failed to send verification email' );
			}

			Log::info( "Verification email sent to: {$user->getEmail()}" );
		}
		catch( \Exception $e )
		{
			Log::error( "Error sending verification email to {$user->getEmail()}: " . $e->getMessage() );
			throw new Exception( 'Failed to send verification email' );
		}
	}
}
