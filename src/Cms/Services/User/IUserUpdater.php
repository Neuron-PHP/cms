<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Dto\Dto;

/**
 * User update service interface
 *
 * @package Neuron\Cms\Services\User
 */
interface IUserUpdater
{
	/**
	 * Update an existing user from DTO
	 *
	 * @param Dto $request DTO containing id, username, email, role, password (optional)
	 * @return User
	 * @throws \Exception
	 */
	public function update( Dto $request ): User;
}
