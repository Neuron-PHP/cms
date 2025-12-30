<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\User;
use Neuron\Cms\Models\EmailVerificationToken;

/**
 * Email verification service interface
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IEmailVerifier
{
	/**
	 * Set token expiration time in minutes
	 *
	 * @param int $minutes Expiration time in minutes
	 * @return self
	 */
	public function setTokenExpirationMinutes( int $minutes ): self;

	/**
	 * Send verification email to user
	 *
	 * @param User $user User to send email to
	 * @return bool True on success
	 */
	public function sendVerificationEmail( User $user ): bool;

	/**
	 * Validate an email verification token
	 *
	 * @param string $plainToken The plain token string
	 * @return EmailVerificationToken|null Token object if valid, null otherwise
	 */
	public function validateToken( string $plainToken ): ?EmailVerificationToken;

	/**
	 * Verify a user's email using a token
	 *
	 * @param string $plainToken The plain token string
	 * @return bool True on success
	 */
	public function verifyEmail( string $plainToken ): bool;

	/**
	 * Resend verification email to a user
	 *
	 * @param string $email User's email address
	 * @return bool True on success
	 */
	public function resendVerification( string $email ): bool;

	/**
	 * Clean up expired email verification tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function cleanupExpiredTokens(): int;
}
