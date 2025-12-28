<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;

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
