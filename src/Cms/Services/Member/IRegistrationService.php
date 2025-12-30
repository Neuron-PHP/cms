<?php

namespace Neuron\Cms\Services\Member;

use Neuron\Cms\Models\User;
use Neuron\Dto\Dto;

/**
 * Registration service interface
 *
 * @package Neuron\Cms\Services\Member
 */
interface IRegistrationService
{
	/**
	 * Check if user registration is enabled
	 *
	 * @return bool
	 */
	public function isRegistrationEnabled(): bool;

	/**
	 * Register a new user
	 *
	 * @param string $username Username
	 * @param string $email Email address
	 * @param string $password Password
	 * @param string $passwordConfirmation Password confirmation
	 * @return User Created user
	 */
	public function register(
		string $username,
		string $email,
		string $password,
		string $passwordConfirmation
	): User;

	/**
	 * Register a new user from DTO
	 *
	 * @param Dto $dto Registration DTO
	 * @return User Created user
	 */
	public function registerWithDto( Dto $dto ): User;
}
