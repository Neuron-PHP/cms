<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;

/**
 * User creation service interface
 *
 * @package Neuron\Cms\Services\User
 */
interface IUserCreator
{
	/**
	 * Create a new user
	 *
	 * @param string $username
	 * @param string $email
	 * @param string $password
	 * @param string $role
	 * @param string|null $timezone
	 * @return User
	 * @throws \Exception
	 */
	public function create(
		string $username,
		string $email,
		string $password,
		string $role,
		?string $timezone = null
	): User;
}

/**
 * User update service interface
 *
 * @package Neuron\Cms\Services\User
 */
interface IUserUpdater
{
	/**
	 * Update an existing user
	 *
	 * @param User $user
	 * @param string $username
	 * @param string $email
	 * @param string $role
	 * @param string|null $password
	 * @param string|null $timezone
	 * @return User
	 * @throws \Exception
	 */
	public function update(
		User $user,
		string $username,
		string $email,
		string $role,
		?string $password = null,
		?string $timezone = null
	): User;
}

/**
 * User deletion service interface
 *
 * @package Neuron\Cms\Services\User
 */
interface IUserDeleter
{
	/**
	 * Delete a user
	 *
	 * @param int $userId
	 * @return bool
	 * @throws \Exception
	 */
	public function delete( int $userId ): bool;
}
