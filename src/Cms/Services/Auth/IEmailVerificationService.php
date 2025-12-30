<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\User;

/**
 * Email verification service interface
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IEmailVerificationService
{
	/**
	 * Send verification email to user
	 *
	 * @param User $user
	 * @return bool True if email sent successfully
	 * @throws \Exception
	 */
	public function sendVerificationEmail( User $user ): bool;

	/**
	 * Verify user's email using token
	 *
	 * @param string $token
	 * @return bool True if verification successful
	 * @throws \Exception
	 */
	public function verifyEmail( string $token ): bool;

	/**
	 * Check if user can request verification email (rate limiting)
	 *
	 * @param int $userId
	 * @return bool True if user can request
	 */
	public function canRequestVerification( int $userId ): bool;

	/**
	 * Get time until user can request verification again
	 *
	 * @param int $userId
	 * @return int Seconds until next request allowed
	 */
	public function getTimeUntilNextRequest( int $userId ): int;
}
