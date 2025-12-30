<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\User;

/**
 * Authentication Service Interface
 *
 * Defines the contract for authentication services
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IAuthenticationService
{
	/**
	 * Attempt to authenticate a user with username and password
	 *
	 * @param string $username
	 * @param string $password
	 * @param bool $remember
	 * @return bool True if authentication successful
	 */
	public function attempt( string $username, string $password, bool $remember = false ): bool;

	/**
	 * Log a user in (assumes user is already authenticated)
	 *
	 * @param User $user
	 * @param bool $remember
	 * @return void
	 */
	public function login( User $user, bool $remember = false ): void;

	/**
	 * Log the current user out
	 *
	 * @return void
	 */
	public function logout(): void;

	/**
	 * Check if a user is currently authenticated
	 *
	 * @return bool
	 */
	public function check(): bool;

	/**
	 * Get the currently authenticated user
	 *
	 * @return User|null
	 */
	public function user(): ?User;

	/**
	 * Get the currently authenticated user's ID
	 *
	 * @return int|null
	 */
	public function id(): ?int;

	/**
	 * Validate user credentials
	 *
	 * @param User $user
	 * @param string $password
	 * @return bool
	 */
	public function validateCredentials( User $user, string $password ): bool;

	/**
	 * Attempt login using remember token
	 *
	 * @param string $token
	 * @return bool
	 */
	public function loginUsingRememberToken( string $token ): bool;

	/**
	 * Set maximum login attempts before lockout
	 *
	 * @param int $maxLoginAttempts
	 * @return self
	 */
	public function setMaxLoginAttempts( int $maxLoginAttempts ): self;

	/**
	 * Set lockout duration in minutes
	 *
	 * @param int $lockoutDuration
	 * @return self
	 */
	public function setLockoutDuration( int $lockoutDuration ): self;

	/**
	 * Check if user has a specific role
	 *
	 * @param string $role
	 * @return bool
	 */
	public function hasRole( string $role ): bool;

	/**
	 * Check if user is admin
	 *
	 * @return bool
	 */
	public function isAdmin(): bool;

	/**
	 * Check if user is editor or higher
	 *
	 * @return bool
	 */
	public function isEditorOrHigher(): bool;

	/**
	 * Check if user is author or higher
	 *
	 * @return bool
	 */
	public function isAuthorOrHigher(): bool;
}
