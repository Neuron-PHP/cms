<?php

namespace Neuron\Cms\Auth;

use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Cms\Repositories\IPasswordResetTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Util\Email;
use Exception;

/**
 * Password reset manager.
 *
 * Handles password reset token generation, validation, and password updates.
 *
 * @package Neuron\Cms\Auth
 */
class PasswordResetManager
{
	private IPasswordResetTokenRepository $_tokenRepository;
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private string $_resetUrl;
	private string $_fromEmail;
	private string $_fromName;
	private int $_tokenExpirationMinutes = 60;

	/**
	 * Constructor
	 *
	 * @param IPasswordResetTokenRepository $tokenRepository Token repository
	 * @param IUserRepository $userRepository User repository
	 * @param PasswordHasher $passwordHasher Password hasher
	 * @param string $resetUrl Base URL for password reset (token will be appended)
	 * @param string $fromEmail Email address to send from
	 * @param string $fromName Name to send from
	 */
	public function __construct(
		IPasswordResetTokenRepository $tokenRepository,
		IUserRepository $userRepository,
		PasswordHasher $passwordHasher,
		string $resetUrl,
		string $fromEmail,
		string $fromName = 'System'
	)
	{
		$this->_tokenRepository = $tokenRepository;
		$this->_userRepository = $userRepository;
		$this->_passwordHasher = $passwordHasher;
		$this->_resetUrl = $resetUrl;
		$this->_fromEmail = $fromEmail;
		$this->_fromName = $fromName;
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

		$subject = 'Password Reset Request';

		$body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { padding: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Password Reset Request</h2>
        </div>
        <div class="content">
            <p>You have requested to reset your password. Click the button below to proceed:</p>
            <a href="{$resetLink}" class="button">Reset Password</a>
            <p>Or copy and paste this link into your browser:</p>
            <p><a href="{$resetLink}">{$resetLink}</a></p>
            <p>This link will expire in {$this->_tokenExpirationMinutes} minutes.</p>
            <p>If you did not request a password reset, please ignore this email.</p>
        </div>
        <div class="footer">
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>
HTML;

		$mail = new Email();
		$mail->setType( Email::EMAIL_HTML );
		$mail->setFrom( "{$this->_fromName} <{$this->_fromEmail}>" );
		$mail->addTo( $email );
		$mail->setSubject( $subject );
		$mail->setBody( $body );

		if( !$mail->send() )
		{
			throw new Exception( 'Failed to send password reset email' );
		}
	}
}
