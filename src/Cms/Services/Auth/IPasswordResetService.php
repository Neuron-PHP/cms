<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\PasswordResetToken;

/**
 * Password reset service interface
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IPasswordResetService
{
	/**
	 * Request a password reset for the given email
	 *
	 * @param string $email
	 * @return bool True if reset email sent
	 * @throws \Exception
	 */
	public function requestReset( string $email ): bool;

	/**
	 * Validate a password reset token
	 *
	 * @param string $token
	 * @return PasswordResetToken|null
	 */
	public function validateToken( string $token ): ?PasswordResetToken;

	/**
	 * Reset password using token
	 *
	 * @param string $token
	 * @param string $newPassword
	 * @return bool True if password reset successful
	 * @throws \Exception
	 */
	public function resetPassword( string $token, string $newPassword ): bool;
}
