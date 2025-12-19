<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use DateTimeImmutable;
use DateInterval;

/**
 * Authentication service.
 *
 * Handles user authentication, session management, and remember me functionality.
 *
 * @package Neuron\Cms\Services\Auth
 */
class Authentication
{
	private IUserRepository $_userRepository;
	private SessionManager $_sessionManager;
	private PasswordHasher $_passwordHasher;
	private int $_maxLoginAttempts = 5;
	private int $_lockoutDuration = 15; // minutes

	public function __construct(
		IUserRepository $userRepository,
		SessionManager $sessionManager,
		PasswordHasher $passwordHasher
	)
	{
		$this->_userRepository = $userRepository;
		$this->_sessionManager = $sessionManager;
		$this->_passwordHasher = $passwordHasher;
	}

	/**
	 * Attempt to authenticate a user
	 */
	public function attempt( string $username, string $password, bool $remember = false ): bool
	{
		$user = $this->_userRepository->findByUsername( $username );

		if( !$user )
		{
			// Perform dummy hash to normalize timing
			$this->_passwordHasher->verify( $password, '$2y$10$dummyhashtopreventtimingattack1234567890' );

			// Emit login failed event
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginFailedEvent(
				$username,
				$_SERVER['REMOTE_ADDR'] ?? 'unknown',
				microtime( true ),
				'user_not_found'
			) );

			return false;
		}

		// Check if account is locked
		if( $user->isLockedOut() )
		{
			// Emit login failed event
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginFailedEvent(
				$username,
				$_SERVER['REMOTE_ADDR'] ?? 'unknown',
				microtime( true ),
				'account_locked'
			) );
			return false;
		}

		// Check if account is active
		if( !$user->isActive() )
		{
			// Emit login failed event
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginFailedEvent(
				$username,
				$_SERVER['REMOTE_ADDR'] ?? 'unknown',
				microtime( true ),
				'account_inactive'
			) );
			return false;
		}

		// Verify password
		if( !$this->validateCredentials( $user, $password ) )
		{
			// Atomically increment failed login attempts to avoid race condition
			$newFailedAttempts = $this->_userRepository->incrementFailedLoginAttempts( $user->getId() );

			// Lock account if too many failed attempts
			if( $newFailedAttempts >= $this->_maxLoginAttempts )
			{
				$lockedUntil = (new DateTimeImmutable())->add( new DateInterval( "PT{$this->_lockoutDuration}M" ) );
				$this->_userRepository->setLockedUntil( $user->getId(), $lockedUntil );
			}

			// Emit login failed event
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginFailedEvent(
				$username,
				$_SERVER['REMOTE_ADDR'] ?? 'unknown',
				microtime( true ),
				'invalid_credentials'
			) );

			return false;
		}

		// Successful login - atomically reset failed attempts
		$this->_userRepository->resetFailedLoginAttempts( $user->getId() );

		// Refresh user from database to get updated failed_login_attempts
		$user = $this->_userRepository->findById( $user->getId() );

		// Update last login time and potentially rehash password
		$user->setLastLoginAt( new DateTimeImmutable() );

		// Check if password needs rehashing
		if( $this->_passwordHasher->needsRehash( $user->getPasswordHash() ) )
		{
			$user->setPasswordHash( $this->_passwordHasher->hash( $password ) );
		}

		$this->_userRepository->update( $user );

		// Log the user in
		$this->login( $user, $remember );

		return true;
	}

	/**
	 * Log a user in
	 */
	public function login( User $user, bool $remember = false ): void
	{
		// Regenerate session ID to prevent session fixation
		$this->_sessionManager->regenerate();

		// Store user ID in session
		$this->_sessionManager->set( 'user_id', $user->getId() );
		$this->_sessionManager->set( 'user_role', $user->getRole() );
		$this->_sessionManager->set( 'login_time', microtime( true ) );

		// Handle remember me
		if( $remember )
		{
			$this->setRememberToken( $user );
		}

		// Emit user login event
		\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginEvent(
			$user,
			$_SERVER['REMOTE_ADDR'] ?? 'unknown',
			microtime( true )
		) );
	}

	/**
	 * Log the current user out
	 */
	public function logout(): void
	{
		$user = null;
		$sessionDuration = 0.0;

		// Clear remember token if exists
		if( $this->check() )
		{
			$user = $this->user();
			if( $user )
			{
				$user->setRememberToken( null );
				$this->_userRepository->update( $user );

				// Calculate session duration
				$loginTime = $this->_sessionManager->get( 'login_time' );
				if( $loginTime )
				{
					$sessionDuration = microtime( true ) - $loginTime;
				}
			}
		}

		// Destroy session
		$this->_sessionManager->destroy();

		// Delete remember me cookie if exists
		if( isset( $_COOKIE['remember_token'] ) )
		{
			setcookie( 'remember_token', '', time() - 3600, '/', '', true, true );
		}

		// Emit user logout event
		if( $user )
		{
			\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLogoutEvent(
				$user,
				$sessionDuration
			) );
		}
	}

	/**
	 * Check if a user is authenticated
	 */
	public function check(): bool
	{
		// Check session first
		if( $this->_sessionManager->has( 'user_id' ) )
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

		$userId = $this->_sessionManager->get( 'user_id' );

		if( !$userId )
		{
			return null;
		}

		$user = $this->_userRepository->findById( $userId );

		if( !$user )
		{
			// Clear stale session if user no longer exists
			$this->logout();
		}

		return $user;
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

		return $this->_sessionManager->get( 'user_id' );
	}

	/**
	 * Validate user credentials
	 */
	public function validateCredentials( User $user, string $password ): bool
	{
		return $this->_passwordHasher->verify( $password, $user->getPasswordHash() );
	}

	/**
	 * Set remember me token for user
	 */
	private function setRememberToken( User $user ): void
	{
		// Generate secure random token
		$token = bin2hex( random_bytes( 32 ) );

		// Hash the token before storing
		$hashedToken = hash( 'sha256', $token );

		// Store hashed token in user record
		$user->setRememberToken( $hashedToken );
		$this->_userRepository->update( $user );

		// Set cookie with plain token (30 days)
		setcookie(
			'remember_token',
			$token,
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
	public function loginUsingRememberToken( string $token ): bool
	{
		// Hash the token to compare with stored hash
		$hashedToken = hash( 'sha256', $token );

		$user = $this->_userRepository->findByRememberToken( $hashedToken );

		if( !$user || !$user->isActive() )
		{
			return false;
		}

		// Log the user in
		$this->login( $user, true );

		return true;
	}

	/**
	 * Set maximum login attempts before lockout
	 */
	public function setMaxLoginAttempts( int $maxLoginAttempts ): self
	{
		$this->_maxLoginAttempts = $maxLoginAttempts;
		return $this;
	}

	/**
	 * Set lockout duration in minutes
	 */
	public function setLockoutDuration( int $lockoutDuration ): self
	{
		$this->_lockoutDuration = $lockoutDuration;
		return $this;
	}

	/**
	 * Check if user has a specific role
	 */
	public function hasRole( string $role ): bool
	{
		$user = $this->user();
		return $user && $user->getRole() === $role;
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
		$user = $this->user();
		if( !$user )
		{
			return false;
		}

		return in_array( $user->getRole(), [User::ROLE_ADMIN, User::ROLE_EDITOR] );
	}

	/**
	 * Check if user is author or higher
	 */
	public function isAuthorOrHigher(): bool
	{
		$user = $this->user();
		if( !$user )
		{
			return false;
		}

		return in_array( $user->getRole(), [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR] );
	}
}
