<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Dto\Dto;

/**
 * User creation service interface
 *
 * @package Neuron\Cms\Services\User
 */
interface IUserCreator
{
	/**
	 * Create a new user from DTO
	 *
	 * @param Dto $request DTO containing username, email, password, role, timezone
	 * @return User
	 * @throws \Exception
	 */
	public function create( Dto $request ): User;
}
