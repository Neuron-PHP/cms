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
