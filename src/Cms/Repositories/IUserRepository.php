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
	public function findById( int $Id ): ?User;

	/**
	 * Find user by username
	 */
	public function findByUsername( string $Username ): ?User;

	/**
	 * Find user by email
	 */
	public function findByEmail( string $Email ): ?User;

	/**
	 * Find user by remember token
	 */
	public function findByRememberToken( string $Token ): ?User;

	/**
	 * Create a new user
	 */
	public function create( User $User ): User;

	/**
	 * Update an existing user
	 */
	public function update( User $User ): bool;

	/**
	 * Delete a user
	 */
	public function delete( int $Id ): bool;

	/**
	 * Get all users
	 */
	public function all(): array;

	/**
	 * Count total users
	 */
	public function count(): int;
}
