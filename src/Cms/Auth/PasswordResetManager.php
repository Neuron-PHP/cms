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
	private IPasswordResetTokenRepository $_TokenRepository;
	private IUserRepository $_UserRepository;
	private PasswordHasher $_PasswordHasher;
	private string $_ResetUrl;
	private string $_FromEmail;
	private string $_FromName;
	private int $_TokenExpirationMinutes = 60;

	/**
	 * Constructor
	 *
	 * @param IPasswordResetTokenRepository $TokenRepository Token repository
	 * @param IUserRepository $UserRepository User repository
	 * @param PasswordHasher $PasswordHasher Password hasher
	 * @param string $ResetUrl Base URL for password reset (token will be appended)
	 * @param string $FromEmail Email address to send from
	 * @param string $FromName Name to send from
	 */
	public function __construct(
		IPasswordResetTokenRepository $TokenRepository,
		IUserRepository $UserRepository,
		PasswordHasher $PasswordHasher,
		string $ResetUrl,
		string $FromEmail,
		string $FromName = 'System'
	)
	{
		$this->_TokenRepository = $TokenRepository;
		$this->_UserRepository = $UserRepository;
		$this->_PasswordHasher = $PasswordHasher;
		$this->_ResetUrl = $ResetUrl;
		$this->_FromEmail = $FromEmail;
		$this->_FromName = $FromName;
	}

	/**
	 * Set token expiration time in minutes
	 */
	public function setTokenExpirationMinutes( int $Minutes ): self
	{
		$this->_TokenExpirationMinutes = $Minutes;
		return $this;
	}

	/**
	 * Request a password reset for a user
	 *
	 * Generates a secure token, stores it, and sends an email to the user.
	 *
	 * @param string $Email User's email address
	 * @return bool True if reset email was sent, false if user not found
	 * @throws Exception if email sending fails
	 */
	public function requestReset( string $Email ): bool
	{
		// Check if user exists
		$User = $this->_UserRepository->findByEmail( $Email );

		if( !$User )
		{
			// Don't reveal whether email exists in system
			return true;
		}

		// Delete any existing tokens for this email
		$this->_TokenRepository->deleteByEmail( $Email );

		// Generate secure random token
		$PlainToken = bin2hex( random_bytes( 32 ) );
		$HashedToken = hash( 'sha256', $PlainToken );

		// Create and store token
		$Token = new PasswordResetToken(
			$Email,
			$HashedToken,
			$this->_TokenExpirationMinutes
		);

		$this->_TokenRepository->create( $Token );

		// Send reset email
		$this->sendResetEmail( $Email, $PlainToken );

		return true;
	}

	/**
	 * Validate a reset token
	 *
	 * @param string $PlainToken Plain text token from URL
	 * @return PasswordResetToken|null Token if valid and not expired, null otherwise
	 */
	public function validateToken( string $PlainToken ): ?PasswordResetToken
	{
		$HashedToken = hash( 'sha256', $PlainToken );

		$Token = $this->_TokenRepository->findByToken( $HashedToken );

		if( !$Token || $Token->isExpired() )
		{
			return null;
		}

		return $Token;
	}

	/**
	 * Reset password using a valid token
	 *
	 * @param string $PlainToken Plain text token from URL
	 * @param string $NewPassword New password to set
	 * @return bool True if password was reset, false if token invalid or expired
	 * @throws Exception if password doesn't meet requirements or update fails
	 */
	public function resetPassword( string $PlainToken, string $NewPassword ): bool
	{
		// Validate token
		$Token = $this->validateToken( $PlainToken );

		if( !$Token )
		{
			return false;
		}

		// Validate new password
		if( !$this->_PasswordHasher->meetsRequirements( $NewPassword ) )
		{
			$Errors = $this->_PasswordHasher->getValidationErrors( $NewPassword );
			throw new Exception( implode( ', ', $Errors ) );
		}

		// Find user
		$User = $this->_UserRepository->findByEmail( $Token->getEmail() );

		if( !$User )
		{
			return false;
		}

		// Update password
		$User->setPasswordHash( $this->_PasswordHasher->hash( $NewPassword ) );
		$this->_UserRepository->update( $User );

		// Delete the token
		$this->_TokenRepository->deleteByToken( hash( 'sha256', $PlainToken ) );

		return true;
	}

	/**
	 * Clean up expired tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function cleanupExpiredTokens(): int
	{
		return $this->_TokenRepository->deleteExpired();
	}

	/**
	 * Send password reset email
	 *
	 * @param string $Email Recipient email
	 * @param string $PlainToken Plain text token to include in URL
	 * @throws Exception if email sending fails
	 */
	private function sendResetEmail( string $Email, string $PlainToken ): void
	{
		$ResetLink = $this->_ResetUrl . '?token=' . urlencode( $PlainToken );

		$Subject = 'Password Reset Request';

		$Body = <<<HTML
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
            <a href="{$ResetLink}" class="button">Reset Password</a>
            <p>Or copy and paste this link into your browser:</p>
            <p><a href="{$ResetLink}">{$ResetLink}</a></p>
            <p>This link will expire in {$this->_TokenExpirationMinutes} minutes.</p>
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
		$mail->setFrom( "{$this->_FromName} <{$this->_FromEmail}>" );
		$mail->addTo( $Email );
		$mail->setSubject( $Subject );
		$mail->setBody( $Body );

		if( !$mail->send() )
		{
			throw new Exception( 'Failed to send password reset email' );
		}
	}
}
