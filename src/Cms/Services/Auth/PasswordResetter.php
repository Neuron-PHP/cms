<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Cms\Repositories\IPasswordResetTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Setting\SettingManager;
use Neuron\Log\Log;
use Exception;

/**
 * Password reset service.
 *
 * Handles password reset token generation, validation, and password updates.
 *
 * @package Neuron\Cms\Services\Auth
 */
class PasswordResetter
{
	private IPasswordResetTokenRepository $_tokenRepository;
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private SettingManager $_settings;
	private string $_basePath;
	private string $_resetUrl;
	private int $_tokenExpirationMinutes = 60;

	/**
	 * Constructor
	 *
	 * @param IPasswordResetTokenRepository $tokenRepository Token repository
	 * @param IUserRepository $userRepository User repository
	 * @param PasswordHasher $passwordHasher Password hasher
	 * @param SettingManager $settings Settings manager with email configuration
	 * @param string $basePath Base path for template loading
	 * @param string $resetUrl Base URL for password reset (token will be appended)
	 */
	public function __construct(
		IPasswordResetTokenRepository $tokenRepository,
		IUserRepository $userRepository,
		PasswordHasher $passwordHasher,
		SettingManager $settings,
		string $basePath,
		string $resetUrl
	)
	{
		$this->_tokenRepository = $tokenRepository;
		$this->_userRepository = $userRepository;
		$this->_passwordHasher = $passwordHasher;
		$this->_settings = $settings;
		$this->_basePath = $basePath;
		$this->_resetUrl = $resetUrl;
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
	 * Request a password reset for a user
	 *
	 * Generates a secure token, stores it, and sends an email to the user.
	 *
	 * @param string $email User's email address
	 * @return bool True if reset email was sent, false if user not found
	 * @throws Exception if email sending fails
	 */
	public function requestReset( string $email ): bool
	{
		// Check if user exists
		$user = $this->_userRepository->findByEmail( $email );

		if( !$user )
		{
			// Don't reveal whether email exists in system
			return true;
		}

		// Delete any existing tokens for this email
		$this->_tokenRepository->deleteByEmail( $email );

		// Generate secure random token
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );

		// Create and store token
		$token = new PasswordResetToken(
			$email,
			$hashedToken,
			$this->_tokenExpirationMinutes
		);

		$this->_tokenRepository->create( $token );

		// Send reset email
		$this->sendResetEmail( $email, $plainToken );

		return true;
	}

	/**
	 * Validate a reset token
	 *
	 * @param string $plainToken Plain text token from URL
	 * @return PasswordResetToken|null Token if valid and not expired, null otherwise
	 */
	public function validateToken( string $plainToken ): ?PasswordResetToken
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
	 * Reset password using a valid token
	 *
	 * @param string $plainToken Plain text token from URL
	 * @param string $newPassword New password to set
	 * @return bool True if password was reset, false if token invalid or expired
	 * @throws Exception if password doesn't meet requirements or update fails
	 */
	public function resetPassword( string $plainToken, string $newPassword ): bool
	{
		// Validate token
		$token = $this->validateToken( $plainToken );

		if( !$token )
		{
			return false;
		}

		// Validate new password
		if( !$this->_passwordHasher->meetsRequirements( $newPassword ) )
		{
			$errors = $this->_passwordHasher->getValidationErrors( $newPassword );
			throw new Exception( implode( ', ', $errors ) );
		}

		// Find user
		$user = $this->_userRepository->findByEmail( $token->getEmail() );

		if( !$user )
		{
			return false;
		}

		// Update password
		$user->setPasswordHash( $this->_passwordHasher->hash( $newPassword ) );
		$this->_userRepository->update( $user );

		// Delete the token
		$this->_tokenRepository->deleteByToken( hash( 'sha256', $plainToken ) );

		return true;
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
	 * Send password reset email
	 *
	 * @param string $email Recipient email
	 * @param string $plainToken Plain text token to include in URL
	 * @throws Exception if email sending fails
	 */
	private function sendResetEmail( string $email, string $plainToken ): void
	{
		$resetLink = $this->_resetUrl . '?token=' . urlencode( $plainToken );

		// Get site name from settings
		$siteName = $this->_settings->get( 'site', 'name' ) ?? 'Neuron CMS';

		// Prepare template data
		$templateData = [
			'ResetLink' => $resetLink,
			'ExpirationMinutes' => $this->_tokenExpirationMinutes,
			'SiteName' => $siteName
		];

		try
		{
			// Send email using Sender service with template
			$sender = new Sender( $this->_settings, $this->_basePath );
			$result = $sender
				->to( $email )
				->subject( "Password Reset Request - {$siteName}" )
				->template( 'emails/password-reset', $templateData )
				->send();

			if( !$result )
			{
				throw new Exception( 'Failed to send password reset email' );
			}

			Log::info( "Password reset email sent to: {$email}" );
		}
		catch( \Exception $e )
		{
			Log::error( "Error sending password reset email to {$email}: " . $e->getMessage() );
			throw new Exception( 'Failed to send password reset email' );
		}
	}
}
