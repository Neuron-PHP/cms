<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\RememberToken;

/**
 * Remember token repository interface.
 *
 * Defines methods for managing "remember me" tokens, one per browser.
 *
 * @package Neuron\Cms\Repositories
 */
interface IRememberTokenRepository
{
	/**
	 * Create a new remember token
	 *
	 * @param RememberToken $token Token to create
	 * @return RememberToken Created token with ID set
	 */
	public function create( RememberToken $token ): RememberToken;

	/**
	 * Find an unexpired token by its hashed value
	 *
	 * @param string $token Hashed token
	 * @return RememberToken|null Token if found and not expired, null otherwise
	 */
	public function findValidByToken( string $token ): ?RememberToken;

	/**
	 * Record that a token was just used to sign in
	 *
	 * @param RememberToken $token Token that was used
	 * @return void
	 */
	public function touch( RememberToken $token ): void;

	/**
	 * Delete a specific token by its hashed value
	 *
	 * @param string $token Hashed token
	 * @return bool True if deleted, false otherwise
	 */
	public function deleteByToken( string $token ): bool;

	/**
	 * Delete every token for a user, signing out all of their browsers
	 *
	 * @param int $userId User ID
	 * @return int Number of tokens deleted
	 */
	public function deleteByUserId( int $userId ): int;

	/**
	 * Delete all expired tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function deleteExpired(): int;
}
