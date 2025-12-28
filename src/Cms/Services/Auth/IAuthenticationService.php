<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Models\User;

/**
 * Authentication service interface
 *
 * @package Neuron\Cms\Services\Auth
 */
interface IAuthenticationService
{
	/**
	 * Attempt to authenticate a user
	 *
	 * @param string $username Username or email
	 * @param string $password Password
	 * @param bool $remember Remember the user
	 * @return bool True if authentication successful
	 */
	public function attempt( string $username, string $password, bool $remember = false ): bool;

	/**
	 * Log out the current user
	 *
	 * @return void
	 */
	public function logout(): void;

	/**
	 * Get the currently authenticated user
	 *
	 * @return User|null
	 */
	public function user(): ?User;

	/**
	 * Check if user is authenticated
	 *
	 * @return bool
	 */
	public function check(): bool;

	/**
	 * Check if user is guest (not authenticated)
	 *
	 * @return bool
	 */
	public function guest(): bool;

	/**
	 * Check if user has specific role
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
