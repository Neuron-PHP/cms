<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\EmailVerificationToken;

/**
 * Email verification token repository interface.
 *
 * Defines methods for managing email verification tokens.
 *
 * @package Neuron\Cms\Repositories
 */
interface IEmailVerificationTokenRepository
{
	/**
	 * Create a new email verification token
	 *
	 * @param EmailVerificationToken $token Token to create
	 * @return EmailVerificationToken Created token with ID set
	 */
	public function create( EmailVerificationToken $token ): EmailVerificationToken;

	/**
	 * Find a token by its hashed value
	 *
	 * @param string $token Hashed token
	 * @return EmailVerificationToken|null Token if found, null otherwise
	 */
	public function findByToken( string $token ): ?EmailVerificationToken;

	/**
	 * Find tokens by user ID
	 *
	 * @param int $userId User ID
	 * @return EmailVerificationToken|null Most recent token if found, null otherwise
	 */
	public function findByUserId( int $userId ): ?EmailVerificationToken;

	/**
	 * Delete all tokens for a given user ID
	 *
	 * @param int $userId User ID
	 * @return int Number of tokens deleted
	 */
	public function deleteByUserId( int $userId ): int;

	/**
	 * Delete a specific token by its hashed value
	 *
	 * @param string $token Hashed token
	 * @return bool True if deleted, false otherwise
	 */
	public function deleteByToken( string $token ): bool;

	/**
	 * Delete all expired tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function deleteExpired(): int;
}
