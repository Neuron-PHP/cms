<?php

/**
 * Authentication helper functions.
 *
 * Provides convenient access to authentication functionality.
 *
 * @package Neuron\Cms\Auth
 */

use Neuron\Patterns\Registry;
use Neuron\Cms\Models\User;

if (!function_exists('auth')) {
	/**
	 * Get the authenticated user
	 */
	function auth(): ?User
	{
		return Registry::getInstance()->get('Auth.User');
	}
}

if (!function_exists('user')) {
	/**
	 * Alias for auth()
	 */
	function user(): ?User
	{
		return auth();
	}
}

if (!function_exists('user_id')) {
	/**
	 * Get the authenticated user's ID
	 */
	function user_id(): ?int
	{
		return Registry::getInstance()->get('Auth.UserId');
	}
}

if (!function_exists('is_logged_in')) {
	/**
	 * Check if user is authenticated
	 */
	function is_logged_in(): bool
	{
		return auth() !== null;
	}
}

if (!function_exists('is_guest')) {
	/**
	 * Check if user is a guest (not authenticated)
	 */
	function is_guest(): bool
	{
		return auth() === null;
	}
}

if (!function_exists('is_admin')) {
	/**
	 * Check if user is an admin
	 */
	function is_admin(): bool
	{
		$User = auth();
		return $User && $User->isAdmin();
	}
}

if (!function_exists('is_editor')) {
	/**
	 * Check if user is an editor
	 */
	function is_editor(): bool
	{
		$User = auth();
		return $User && $User->isEditor();
	}
}

if (!function_exists('is_author')) {
	/**
	 * Check if user is an author
	 */
	function is_author(): bool
	{
		$User = auth();
		return $User && $User->isAuthor();
	}
}

if (!function_exists('has_role')) {
	/**
	 * Check if user has a specific role
	 */
	function has_role(string $Role): bool
	{
		$User = auth();
		return $User && $User->getRole() === $Role;
	}
}

if (!function_exists('csrf_token')) {
	/**
	 * Get the current CSRF token
	 */
	function csrf_token(): string
	{
		return Registry::getInstance()->get('Auth.CsrfToken') ?? '';
	}
}

if (!function_exists('csrf_field')) {
	/**
	 * Generate a CSRF token hidden input field
	 */
	function csrf_field(): string
	{
		$Token = csrf_token();
		return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($Token) . '">';
	}
}
