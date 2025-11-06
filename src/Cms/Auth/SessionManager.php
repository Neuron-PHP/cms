<?php

namespace Neuron\Cms\Auth;

/**
 * Session management utility.
 *
 * Handles session initialization, regeneration, flash messages,
 * and secure session configuration.
 *
 * @package Neuron\Cms\Auth
 */
class SessionManager
{
	private bool $_Started = false;
	private array $_Config = [];

	public function __construct( array $Config = [] )
	{
		$this->_Config = array_merge([
			'lifetime' => 7200,          // 2 hours
			'cookie_httponly' => true,
			'cookie_secure' => true,     // HTTPS only
			'cookie_samesite' => 'Lax',
			'use_strict_mode' => true
		], $Config);
	}

	/**
	 * Start the session with secure configuration
	 */
	public function start(): void
	{
		if( $this->_Started || session_status() === PHP_SESSION_ACTIVE )
		{
			return;
		}

		// Configure session security
		ini_set( 'session.cookie_httponly', $this->_Config['cookie_httponly'] ? '1' : '0' );
		ini_set( 'session.cookie_secure', $this->_Config['cookie_secure'] ? '1' : '0' );
		ini_set( 'session.cookie_samesite', $this->_Config['cookie_samesite'] );
		ini_set( 'session.use_strict_mode', $this->_Config['use_strict_mode'] ? '1' : '0' );

		session_set_cookie_params([
			'lifetime' => $this->_Config['lifetime'],
			'path' => '/',
			'secure' => $this->_Config['cookie_secure'],
			'httponly' => $this->_Config['cookie_httponly'],
			'samesite' => $this->_Config['cookie_samesite']
		]);

		session_start();
		$this->_Started = true;
	}

	/**
	 * Regenerate session ID (prevent session fixation)
	 */
	public function regenerate( bool $DeleteOldSession = true ): bool
	{
		$this->start();
		return session_regenerate_id( $DeleteOldSession );
	}

	/**
	 * Destroy the session
	 */
	public function destroy(): bool
	{
		$this->start();

		$_SESSION = [];

		// Delete session cookie
		if( isset( $_COOKIE[session_name()] ) )
		{
			$Params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$Params['path'],
				$Params['domain'],
				$Params['secure'],
				$Params['httponly']
			);
		}

		return session_destroy();
	}

	/**
	 * Set a session value
	 */
	public function set( string $Key, mixed $Value ): void
	{
		$this->start();
		$_SESSION[ $Key ] = $Value;
	}

	/**
	 * Get a session value
	 */
	public function get( string $Key, mixed $Default = null ): mixed
	{
		$this->start();
		return $_SESSION[ $Key ] ?? $Default;
	}

	/**
	 * Check if session has a key
	 */
	public function has( string $Key ): bool
	{
		$this->start();
		return isset( $_SESSION[ $Key ] );
	}

	/**
	 * Remove a session value
	 */
	public function remove( string $Key ): void
	{
		$this->start();
		unset( $_SESSION[ $Key ] );
	}

	/**
	 * Set a flash message (available for next request only)
	 */
	public function flash( string $Key, mixed $Value ): void
	{
		$this->start();
		$_SESSION['_flash'][ $Key ] = $Value;
	}

	/**
	 * Get a flash message
	 */
	public function getFlash( string $Key, mixed $Default = null ): mixed
	{
		$this->start();
		$Value = $_SESSION['_flash'][ $Key ] ?? $Default;
		unset( $_SESSION['_flash'][ $Key ] );
		return $Value;
	}

	/**
	 * Check if flash message exists
	 */
	public function hasFlash( string $Key ): bool
	{
		$this->start();
		return isset( $_SESSION['_flash'][ $Key ] );
	}

	/**
	 * Get all flash messages and clear them
	 */
	public function getAllFlash(): array
	{
		$this->start();
		$Flash = $_SESSION['_flash'] ?? [];
		unset( $_SESSION['_flash'] );
		return $Flash;
	}

	/**
	 * Get session ID
	 */
	public function getId(): string
	{
		$this->start();
		return session_id();
	}

	/**
	 * Check if session is started
	 */
	public function isStarted(): bool
	{
		return $this->_Started || session_status() === PHP_SESSION_ACTIVE;
	}
}
