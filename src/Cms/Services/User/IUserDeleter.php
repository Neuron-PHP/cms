<?php

namespace Neuron\Cms\Services\User;

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
