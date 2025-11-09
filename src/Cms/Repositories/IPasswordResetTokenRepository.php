<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\PasswordResetToken;

/**
 * Password reset token repository interface.
 *
 * Defines methods for managing password reset tokens.
 *
 * @package Neuron\Cms\Repositories
 */
interface IPasswordResetTokenRepository
{
	/**
	 * Create a new password reset token
	 *
	 * @param PasswordResetToken $Token Token to create
	 * @return PasswordResetToken Created token with ID set
	 */
	public function create( PasswordResetToken $Token ): PasswordResetToken;

	/**
	 * Find a token by its hashed value
	 *
	 * @param string $Token Hashed token
	 * @return PasswordResetToken|null Token if found, null otherwise
	 */
	public function findByToken( string $Token ): ?PasswordResetToken;

	/**
	 * Delete all tokens for a given email address
	 *
	 * @param string $Email Email address
	 * @return int Number of tokens deleted
	 */
	public function deleteByEmail( string $Email ): int;

	/**
	 * Delete a specific token by its hashed value
	 *
	 * @param string $Token Hashed token
	 * @return bool True if deleted, false otherwise
	 */
	public function deleteByToken( string $Token ): bool;

	/**
	 * Delete all expired tokens
	 *
	 * @return int Number of tokens deleted
	 */
	public function deleteExpired(): int;
}
