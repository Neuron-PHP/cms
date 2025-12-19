<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\User;

/**
 * User repository interface.
 *
 * @package Neuron\Cms\Repositories
 */
interface IUserRepository
{
	/**
	 * Find user by ID
	 */
	public function findById( int $id ): ?User;

	/**
	 * Find user by username
	 */
	public function findByUsername( string $username ): ?User;

	/**
	 * Find user by email
	 */
	public function findByEmail( string $email ): ?User;

	/**
	 * Find user by remember token
	 */
	public function findByRememberToken( string $token ): ?User;

	/**
	 * Create a new user
	 */
	public function create( User $user ): User;

	/**
	 * Update an existing user
	 */
	public function update( User $user ): bool;

	/**
	 * Delete a user
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all users
	 */
	public function all(): array;

	/**
	 * Count total users
	 */
	public function count(): int;

	/**
	 * Atomically increment failed login attempts for a user
	 *
	 * @param int $userId User ID
	 * @return int New failed login attempts count, or -1 if user not found
	 */
	public function incrementFailedLoginAttempts( int $userId ): int;

	/**
	 * Atomically reset failed login attempts and unlock account
	 *
	 * @param int $userId User ID
	 * @return bool True if successful, false if user not found
	 */
	public function resetFailedLoginAttempts( int $userId ): bool;

	/**
	 * Atomically set account lockout until specified time
	 *
	 * @param int $userId User ID
	 * @param \DateTimeImmutable|null $lockedUntil Locked until time, or null to unlock
	 * @return bool True if successful, false if user not found
	 */
	public function setLockedUntil( int $userId, ?\DateTimeImmutable $lockedUntil ): bool;
}
