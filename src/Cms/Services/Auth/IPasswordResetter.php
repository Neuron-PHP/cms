<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\PasswordResetToken;

/**
 * Password reset service interface
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IPasswordResetter
{
	/**
	 * Set token expiration time in minutes
	 *
	 * @param int $minutes Expiration time in minutes
	 * @return self
	 */
	public function setTokenExpirationMinutes( int $minutes ): self;

	/**
	 * Request a password reset for the given email
	 *
	 * @param string $email User email address
	 * @return bool True on success
	 */
	public function requestReset( string $email ): bool;

	/**
	 * Validate a password reset token
	 *
	 * @param string $plainToken The plain token string
	 * @return PasswordResetToken|null Token object if valid, null otherwise
	 */
	public function validateToken( string $plainToken ): ?PasswordResetToken;

	/**
	 * Reset a user's password using a token
	 *
	 * @param string $plainToken The plain token string
	 * @param string $newPassword The new password
	 * @return bool True on success
	 */
	public function resetPassword( string $plainToken, string $newPassword ): bool;

	/**
	 * Clean up expired password reset tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function cleanupExpiredTokens(): int;
}
