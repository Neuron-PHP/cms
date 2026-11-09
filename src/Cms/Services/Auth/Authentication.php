<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\RememberToken;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IRememberTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Log\Log;
use DateTimeImmutable;
use DateInterval;
use Neuron\Cms\Enums\UserRole;

/**
 * Authentication service.
 *
 * Handles user authentication, session management, and remember me functionality.
 *
 * @package Neuron\Cms\Services\Auth
 */
class Authentication implements IAuthenticationService
{
	private IUserRepository $_userRepository;
	private SessionManager $_sessionManager;
	private PasswordHasher $_passwordHasher;
	private ?IRememberTokenRepository $_rememberTokens;
	private int $_maxLoginAttempts = 5;
	private int $_lockoutDuration = 15; // minutes
	private bool $_throttleEnabled = true;
	private int $_rememberLifetime = 43200; // minutes (30 days)
	private bool $_rememberEnabled = true;

	public const REMEMBER_COOKIE = 'remember_token';

	/**
	 * @param IUserRepository $userRepository
	 * @param SessionManager $sessionManager
	 * @param PasswordHasher $passwordHasher
	 * @param IRememberTokenRepository|null $rememberTokens Without it, "remember me" is unavailable.
	 */
	public function __construct(
		IUserRepository $userRepository,
		SessionManager $sessionManager,
		PasswordHasher $passwordHasher,
		?IRememberTokenRepository $rememberTokens = null
	)
	{
		$this->_userRepository = $userRepository;
		$this->_sessionManager = $sessionManager;
		$this->_passwordHasher = $passwordHasher;
		$this->_rememberTokens = $rememberTokens;
	}

	/**
	 * Attempt to authenticate a user
	 *
	 * Accepts either username or email address for login.
	 * Automatically detects if the input is an email and searches accordingly.
	 */
	public function attempt( string $username, string $password, bool $remember = false ): bool
	{
		// Detect if input is an email address
		$isEmail = filter_var( $username, FILTER_VALIDATE_EMAIL );

		// Try to find user by email or username
		if( $isEmail )
		{
			$user = $this->_userRepository->findByEmail( $username );
		}
		else
		{
			$user = $this->_userRepository->findByUsername( $username );
		}

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
		if( $this->_throttleEnabled && $user->isLockedOut() )
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
			if( $this->_throttleEnabled )
			{
				// Atomically increment failed login attempts to avoid race condition
				$newFailedAttempts = $this->_userRepository->incrementFailedLoginAttempts( $user->getId() );

				// Lock account if too many failed attempts
				if( $newFailedAttempts >= $this->_maxLoginAttempts )
				{
					$lockedUntil = (new DateTimeImmutable())->add( new DateInterval( "PT{$this->_lockoutDuration}M" ) );
					$this->_userRepository->setLockedUntil( $user->getId(), $lockedUntil );
				}
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
		$this->startSession( $user );

		if( $remember )
		{
			$this->issueRememberToken( $user );
		}
	}

	/**
	 * Log the current user out
	 */
	public function logout(): void
	{
		$user = null;
		$sessionDuration = 0.0;

		if( $this->check() )
		{
			$user = $this->user();
			if( $user )
			{
				// Tokens not yet moved out of the users table are cleared here.
				if( $user->getRememberToken() !== null )
				{
					$user->setRememberToken( null );
					$this->_userRepository->update( $user );
				}

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

		// Forget this browser only; the user's other browsers stay signed in.
		if( isset( $_COOKIE[ self::REMEMBER_COOKIE ] ) )
		{
			$cookie = (string)$_COOKIE[ self::REMEMBER_COOKIE ];
			$this->withRememberTokens( fn( IRememberTokenRepository $tokens ) => $tokens->deleteByToken( hash( 'sha256', $cookie ) ) );
			$this->forgetRememberCookie();
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
		if( isset( $_COOKIE[ self::REMEMBER_COOKIE ] ) )
		{
			return $this->loginUsingRememberToken( (string)$_COOKIE[ self::REMEMBER_COOKIE ] );
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
	 * Attempt to log in using remember token
	 *
	 * The token is not replaced on use. Several requests from one browser can
	 * arrive at once without a session (after a deploy, or once the session
	 * expires); replacing the token on each would leave the browser holding a
	 * different token from the one stored, and sign it out.
	 */
	public function loginUsingRememberToken( string $token ): bool
	{
		if( !$this->_rememberEnabled )
		{
			$this->forgetRememberCookie();
			return false;
		}

		$hashedToken = hash( 'sha256', $token );
		$remembered = $this->withRememberTokens( fn( IRememberTokenRepository $tokens ) => $tokens->findValidByToken( $hashedToken ) );

		$user = $remembered
			? $this->_userRepository->findById( $remembered->getUserId() )
			: $this->findLegacyRememberedUser( $hashedToken );

		if( !$user || !$user->isActive() )
		{
			// Unknown, expired or revoked: stop the browser sending it.
			$this->forgetRememberCookie();
			return false;
		}

		if( $remembered )
		{
			$this->withRememberTokens( fn( IRememberTokenRepository $tokens ) => $tokens->touch( $remembered ) );
		}

		$this->startSession( $user );

		return true;
	}

	/**
	 * Set how long a "remember me" sign-in lasts
	 *
	 * @param int $minutes Lifetime in minutes (auth.remember.lifetime)
	 */
	public function setRememberLifetime( int $minutes ): self
	{
		$this->_rememberLifetime = max( 1, $minutes );
		return $this;
	}

	/**
	 * Turn "remember me" on or off (auth.remember.enabled)
	 *
	 * When off, the checkbox is ignored and existing remember cookies no
	 * longer sign anyone in.
	 */
	public function setRememberEnabled( bool $enabled ): self
	{
		$this->_rememberEnabled = $enabled;
		return $this;
	}

	/**
	 * Start an authenticated session for the user
	 */
	private function startSession( User $user ): void
	{
		// Regenerate session ID to prevent session fixation
		$this->_sessionManager->regenerate();

		// Store user ID in session
		$this->_sessionManager->set( 'user_id', $user->getId() );
		$this->_sessionManager->set( 'user_role', $user->getRole() );
		$this->_sessionManager->set( 'login_time', microtime( true ) );

		// Emit user login event
		\Neuron\Application\CrossCutting\Event::emit( new \Neuron\Cms\Events\UserLoginEvent(
			$user,
			$_SERVER['REMOTE_ADDR'] ?? 'unknown',
			microtime( true )
		) );
	}

	/**
	 * Give this browser its own remember me token
	 */
	private function issueRememberToken( User $user ): void
	{
		if( !$this->_rememberEnabled )
		{
			return;
		}

		if( $this->_rememberTokens === null )
		{
			Log::warning( 'Remember me requested but no remember token repository is configured.' );
			return;
		}

		$token = bin2hex( random_bytes( 32 ) );
		$previous = isset( $_COOKIE[ self::REMEMBER_COOKIE ] ) ? hash( 'sha256', (string)$_COOKIE[ self::REMEMBER_COOKIE ] ) : null;

		$stored = $this->withRememberTokens( function( IRememberTokenRepository $tokens ) use ( $user, $token, $previous ) {
			// Signing in again on a browser that already has a token replaces it.
			if( $previous !== null )
			{
				$tokens->deleteByToken( $previous );
			}

			// Only the hash is stored; the browser keeps the plain token.
			return $tokens->create( new RememberToken( (int)$user->getId(), hash( 'sha256', $token ), $this->_rememberLifetime ) );
		} );

		if( $stored === null )
		{
			return;
		}

		setcookie(
			self::REMEMBER_COOKIE,
			$token,
			time() + $this->_rememberLifetime * 60,
			'/',
			'',
			true,  // Secure
			true   // HTTPOnly
		);

		// Keep this request consistent with the cookie the browser will send next.
		$_COOKIE[ self::REMEMBER_COOKIE ] = $token;
	}

	/**
	 * Find a user by a token stored before remember_tokens existed
	 *
	 * The token moves to remember_tokens on first use, so existing cookies keep
	 * working through the upgrade.
	 */
	private function findLegacyRememberedUser( string $hashedToken ): ?User
	{
		$user = $this->_userRepository->findByRememberToken( $hashedToken );

		if( !$user || $this->_rememberTokens === null )
		{
			return $user;
		}

		// A concurrent request may have moved it first (unique token); either way
		// the token is only cleared from users once it is safely in remember_tokens.
		$moved = $this->withRememberTokens( function( IRememberTokenRepository $tokens ) use ( $user, $hashedToken ) {
			return $tokens->findValidByToken( $hashedToken )
				?? $tokens->create( new RememberToken( (int)$user->getId(), $hashedToken, $this->_rememberLifetime ) );
		} );

		if( $moved !== null )
		{
			$user->setRememberToken( null );
			$this->_userRepository->update( $user );
		}

		return $user;
	}

	/**
	 * Run a remember token operation, tolerating a missing remember_tokens table
	 *
	 * A site that updated the package but has not run its migrations yet would
	 * otherwise fail every sign-in. Remember me is skipped instead, and logged.
	 *
	 * @template T
	 * @param callable(IRememberTokenRepository): T $operation
	 * @return T|null Null when there is no repository or the operation failed.
	 */
	private function withRememberTokens( callable $operation ): mixed
	{
		if( $this->_rememberTokens === null )
		{
			return null;
		}

		try
		{
			return $operation( $this->_rememberTokens );
		}
		catch( \PDOException $e )
		{
			Log::error( 'Remember me unavailable (has the remember_tokens migration run?): ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Expire the remember me cookie in the browser and for this request
	 */
	private function forgetRememberCookie(): void
	{
		setcookie( self::REMEMBER_COOKIE, '', time() - 3600, '/', '', true, true );
		unset( $_COOKIE[ self::REMEMBER_COOKIE ] );
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
	 * Turn login throttling on or off. When off, failed logins are not
	 * counted and existing lockouts are not enforced.
	 */
	public function setThrottleEnabled( bool $enabled ): self
	{
		$this->_throttleEnabled = $enabled;
		return $this;
	}

	/**
	 * Whether login throttling is on
	 */
	public function isThrottleEnabled(): bool
	{
		return $this->_throttleEnabled;
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
		return $this->hasRole( UserRole::ADMIN->value );
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

		return in_array( $user->getRole(), [UserRole::ADMIN->value, UserRole::EDITOR->value] );
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

		return in_array( $user->getRole(), [UserRole::ADMIN->value, UserRole::EDITOR->value, UserRole::AUTHOR->value] );
	}
}
