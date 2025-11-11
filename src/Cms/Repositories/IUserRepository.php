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
}
