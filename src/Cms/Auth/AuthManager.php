<?php

namespace Neuron\Cms\Auth;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use DateTimeImmutable;
use DateInterval;

/**
 * Authentication manager.
 *
 * Handles user authentication, session management, and remember me functionality.
 *
 * @package Neuron\Cms\Auth
 */
class AuthManager
{
	private IUserRepository $_UserRepository;
	private SessionManager $_SessionManager;
	private PasswordHasher $_PasswordHasher;
	private int $_MaxLoginAttempts = 5;
	private int $_LockoutDuration = 15; // minutes

	public function __construct(
		IUserRepository $UserRepository,
		SessionManager $SessionManager,
		PasswordHasher $PasswordHasher
	)
	{
		$this->_UserRepository = $UserRepository;
		$this->_SessionManager = $SessionManager;
		$this->_PasswordHasher = $PasswordHasher;
	}

	/**
	 * Attempt to authenticate a user
	 */
	public function attempt( string $Username, string $Password, bool $Remember = false ): bool
	{
		$User = $this->_UserRepository->findByUsername( $Username );

		if( !$User )
		{
			// Perform dummy hash to normalize timing
			$this->_PasswordHasher->verify( $Password, '$2y$10$dummyhashtopreventtimingattack1234567890' );
			return false;
		}

		// Check if account is locked
		if( $User->isLockedOut() )
		{
			return false;
		}

		// Check if account is active
		if( !$User->isActive() )
		{
			return false;
		}

		// Verify password
		if( !$this->validateCredentials( $User, $Password ) )
		{
			// Increment failed login attempts
			$User->incrementFailedLoginAttempts();

			// Lock account if too many failed attempts
			if( $User->getFailedLoginAttempts() >= $this->_MaxLoginAttempts )
			{
				$LockedUntil = (new DateTimeImmutable())->add( new DateInterval( "PT{$this->_LockoutDuration}M" ) );
				$User->setLockedUntil( $LockedUntil );
			}

			$this->_UserRepository->update( $User );
			return false;
		}

		// Successful login - reset failed attempts
		$User->resetFailedLoginAttempts();
		$User->setLastLoginAt( new DateTimeImmutable() );

		// Check if password needs rehashing
		if( $this->_PasswordHasher->needsRehash( $User->getPasswordHash() ) )
		{
			$User->setPasswordHash( $this->_PasswordHasher->hash( $Password ) );
		}

		$this->_UserRepository->update( $User );

		// Log the user in
		$this->login( $User, $Remember );

		return true;
	}

	/**
	 * Log a user in
	 */
	public function login( User $User, bool $Remember = false ): void
	{
		// Regenerate session ID to prevent session fixation
		$this->_SessionManager->regenerate();

		// Store user ID in session
		$this->_SessionManager->set( 'user_id', $User->getId() );
		$this->_SessionManager->set( 'user_role', $User->getRole() );

		// Handle remember me
		if( $Remember )
		{
			$this->setRememberToken( $User );
		}
	}

	/**
	 * Log the current user out
	 */
	public function logout(): void
	{
		// Clear remember token if exists
		if( $this->check() )
		{
			$User = $this->user();
			if( $User )
			{
				$User->setRememberToken( null );
				$this->_UserRepository->update( $User );
			}
		}

		// Destroy session
		$this->_SessionManager->destroy();

		// Delete remember me cookie if exists
		if( isset( $_COOKIE['remember_token'] ) )
		{
			setcookie( 'remember_token', '', time() - 3600, '/', '', true, true );
		}
	}

	/**
	 * Check if a user is authenticated
	 */
	public function check(): bool
	{
		// Check session first
		if( $this->_SessionManager->has( 'user_id' ) )
		{
			return true;
		}

		// Check remember me cookie
		if( isset( $_COOKIE['remember_token'] ) )
		{
			return $this->loginUsingRememberToken( $_COOKIE['remember_token'] );
		}

		return false;
	}

	/**
	 * Get the currently authenticated user
	 */
	public function user(): ?User
	{
		if( !$this->check() )
		{
			return null;
		}

		$UserId = $this->_SessionManager->get( 'user_id' );

		if( !$UserId )
		{
			return null;
		}

		return $this->_UserRepository->findById( $UserId );
	}

	/**
	 * Get the current user's ID
	 */
	public function id(): ?int
	{
		if( !$this->check() )
		{
			return null;
		}
		
		return $this->_SessionManager->get( 'user_id' );
	}

	/**
	 * Validate user credentials
	 */
	public function validateCredentials( User $User, string $Password ): bool
	{
		return $this->_PasswordHasher->verify( $Password, $User->getPasswordHash() );
	}

	/**
	 * Set remember me token for user
	 */
	private function setRememberToken( User $User ): void
	{
		// Generate secure random token
		$Token = bin2hex( random_bytes( 32 ) );

		// Hash the token before storing
		$HashedToken = hash( 'sha256', $Token );

		// Store hashed token in user record
		$User->setRememberToken( $HashedToken );
		$this->_UserRepository->update( $User );

		// Set cookie with plain token (30 days)
		setcookie(
			'remember_token',
			$Token,
			time() + (30 * 24 * 60 * 60),
			'/',
			'',
			true,  // Secure
			true   // HTTPOnly
		);
	}

	/**
	 * Attempt to log in using remember token
	 */
	public function loginUsingRememberToken( string $Token ): bool
	{
		// Hash the token to compare with stored hash
		$HashedToken = hash( 'sha256', $Token );

		$User = $this->_UserRepository->findByRememberToken( $HashedToken );

		if( !$User || !$User->isActive() )
		{
			return false;
		}

		// Log the user in
		$this->login( $User, true );

		return true;
	}

	/**
	 * Set maximum login attempts before lockout
	 */
	public function setMaxLoginAttempts( int $MaxLoginAttempts ): self
	{
		$this->_MaxLoginAttempts = $MaxLoginAttempts;
		return $this;
	}

	/**
	 * Set lockout duration in minutes
	 */
	public function setLockoutDuration( int $LockoutDuration ): self
	{
		$this->_LockoutDuration = $LockoutDuration;
		return $this;
	}

	/**
	 * Check if user has a specific role
	 */
	public function hasRole( string $Role ): bool
	{
		$User = $this->user();
		return $User && $User->getRole() === $Role;
	}

	/**
	 * Check if user is admin
	 */
	public function isAdmin(): bool
	{
		return $this->hasRole( User::ROLE_ADMIN );
	}

	/**
	 * Check if user is editor or higher
	 */
	public function isEditorOrHigher(): bool
	{
		$User = $this->user();
		if( !$User )
		{
			return false;
		}

		return in_array( $User->getRole(), [User::ROLE_ADMIN, User::ROLE_EDITOR] );
	}

	/**
	 * Check if user is author or higher
	 */
	public function isAuthorOrHigher(): bool
	{
		$User = $this->user();
		if( !$User )
		{
			return false;
		}

		return in_array( $User->getRole(), [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR] );
	}
}
