<?php

/**
 * Authentication helper functions.
 *
 * Provides convenient access to authentication functionality.
 *
 * @package Neuron\Cms\Auth
 */

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Patterns\Registry;
use Neuron\Cms\Models\User;

if( !function_exists( 'auth' ) )
{
	/**
	 * Get the authenticated user
	 */
	function auth(): ?User
	{
		return Registry::getInstance()->get( RegistryKeys::AUTH_USER );
	}
}

if( !function_exists( 'user' ) )
{
	/**
	 * Alias for auth()
	 */
	function user(): ?User
	{
		return auth();
	}
}

if( !function_exists( 'user_id' ) )
{
	/**
	 * Get the authenticated user's ID
	 */
	function user_id(): ?int
	{
		return Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID );
	}
}

if( !function_exists( 'is_logged_in' ) )
{
	/**
	 * Check if user is authenticated
	 */
	function is_logged_in(): bool
	{
		return auth() !== null;
	}
}

if( !function_exists( 'is_guest' ) )
{
	/**
	 * Check if user is a guest (not authenticated)
	 */
	function is_guest(): bool
	{
		return auth() === null;
	}
}

if( !function_exists( 'is_admin' ) )
{
	/**
	 * Check if user is an admin
	 */
	function is_admin(): bool
	{
		$user = auth();
		return $user && $user->isAdmin();
	}
}

if( !function_exists( 'is_editor' ) )
{
	/**
	 * Check if user is an editor
	 */
	function is_editor(): bool
	{
		$user = auth();
		return $user && $user->isEditor();
	}
}

if( !function_exists( 'is_author' ) )
{
	/**
	 * Check if user is an author
	 */
	function is_author(): bool
	{
		$user = auth();
		return $user && $user->isAuthor();
	}
}

if( !function_exists( 'has_role' ) )
{
	/**
	 * Check if user has a specific role
	 */
	function has_role( string $role ): bool
	{
		$user = auth();
		return $user && $user->getRole() === $role;
	}
}

if( !function_exists( 'csrf_token' ) )
{
	/**
	 * Get the current CSRF token
	 */
	function csrf_token(): string
	{
		return Registry::getInstance()->get( RegistryKeys::AUTH_CSRF_TOKEN ) ?? '';
	}
}

if( !function_exists( 'csrf_field' ) )
{
	/**
	 * Generate a CSRF token hidden input field
	 */
	function csrf_field(): string
	{
		$token = csrf_token();
		return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars( $token ) . '">';
	}
}

if( !function_exists( 'current_user_identifier' ) )
{
	/**
	 * Get current user identifier for audit purposes
	 *
	 * Returns the authenticated CMS user's username if available (web context),
	 * otherwise returns the OS user running the process (CLI context).
	 *
	 * @return string User identifier (e.g., "admin" or "www-data" or "system")
	 */
	function current_user_identifier(): string
	{
		// Try to get authenticated CMS user first (web context)
		$authUser = user();

		if( $authUser !== null )
		{
			return $authUser->getUsername();
		}

		// Fall back to OS user (CLI context)
		$osUser = get_current_user();

		if( !empty( $osUser ) )
		{
			return $osUser;
		}

		// Ultimate fallback
		return 'system';
	}
}
