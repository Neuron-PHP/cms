<?php

namespace Neuron\Cms\Auth;

use Neuron\Log\Log;

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
	private bool $_started = false;

	/** @var array<string, mixed> */
	private array $_config = [];

	/**
	 * Recognised keys:
	 * - lifetime (int, seconds): cookie lifetime and server-side session lifetime
	 * - expire_on_close (bool): session cookie that ends with the browser
	 * - cookie_name (string): session cookie name (PHP default PHPSESSID)
	 * - cookie_secure (bool|'auto'): 'auto' (default) sets it on HTTPS requests
	 * - cookie_httponly (bool), cookie_samesite (Lax|Strict|None)
	 * - test_mode (bool): never touch the real PHP session
	 *
	 * @param array<string, mixed> $config
	 */
	public function __construct( array $config = [] )
	{
		$this->_config = array_merge([
			'lifetime' => 7200,          // 2 hours
			'expire_on_close' => false,
			'cookie_name' => null,
			'cookie_httponly' => true,
			'cookie_secure' => 'auto',
			'cookie_samesite' => 'Lax',
			'use_strict_mode' => true
		], $config);

		if( $this->_config['cookie_secure'] === 'auto' || $this->_config['cookie_secure'] === null )
		{
			// Auto-detect if connection is secure
			$this->_config['cookie_secure'] = !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';
		}

		$this->_config['cookie_secure'] = (bool)$this->_config['cookie_secure'];

		// Browsers reject SameSite=None cookies that are not also Secure
		if( $this->_config['cookie_samesite'] === 'None' && !$this->_config['cookie_secure'] )
		{
			Log::warning( 'Session cookie_samesite None needs a secure cookie; using Lax.' );
			$this->_config['cookie_samesite'] = 'Lax';
		}
	}

	/**
	 * The effective configuration (after 'auto' is resolved).
	 *
	 * @return array<string, mixed>
	 */
	public function getConfig(): array
	{
		return $this->_config;
	}

	/**
	 * Start the session with secure configuration
	 */
	public function start(): void
	{
		if( $this->_started || session_status() === PHP_SESSION_ACTIVE )
		{
			return;
		}

		// Skip actual session start in test mode
		if( isset( $this->_config['test_mode'] ) && $this->_config['test_mode'] )
		{
			$this->_started = true;
			return;
		}

		$lifetime = (int)$this->_config['lifetime'];
		$cookieLifetime = $this->_config['expire_on_close'] ? 0 : $lifetime;

		if( is_string( $this->_config['cookie_name'] ) && $this->_config['cookie_name'] !== '' )
		{
			session_name( $this->_config['cookie_name'] );
		}

		// Keep the server-side session as long as the cookie
		if( $lifetime > 0 )
		{
			ini_set( 'session.gc_maxlifetime', (string)$lifetime );
		}

		// Configure session security
		ini_set( 'session.cookie_httponly', $this->_config['cookie_httponly'] ? '1' : '0' );
		ini_set( 'session.cookie_secure', $this->_config['cookie_secure'] ? '1' : '0' );
		ini_set( 'session.cookie_samesite', $this->_config['cookie_samesite'] );
		ini_set( 'session.use_strict_mode', $this->_config['use_strict_mode'] ? '1' : '0' );

		session_set_cookie_params([
			'lifetime' => $cookieLifetime,
			'path' => '/',
			'secure' => $this->_config['cookie_secure'],
			'httponly' => $this->_config['cookie_httponly'],
			'samesite' => $this->_config['cookie_samesite']
		]);

		session_start();
		$this->_started = true;
	}

	/**
	 * Regenerate session ID (prevent session fixation)
	 */
	public function regenerate( bool $deleteOldSession = true ): bool
	{
		$this->start();

		// Skip actual session regeneration in test mode
		if( isset( $this->_config['test_mode'] ) && $this->_config['test_mode'] )
		{
			return true;
		}

		return session_regenerate_id( $deleteOldSession );
	}

	/**
	 * Destroy the session
	 */
	public function destroy(): bool
	{
		$this->start();

		$_SESSION = [];

		// Skip actual session destruction in test mode
		if( isset( $this->_config['test_mode'] ) && $this->_config['test_mode'] )
		{
			$this->_started = false;
			return true;
		}

		// Delete session cookie
		if( isset( $_COOKIE[session_name()] ) )
		{
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		return session_destroy();
	}

	/**
	 * Set a session value
	 */
	public function set( string $key, mixed $value ): void
	{
		$this->start();
		$_SESSION[ $key ] = $value;
	}

	/**
	 * Get a session value
	 */
	public function get( string $key, mixed $default = null ): mixed
	{
		$this->start();
		return $_SESSION[ $key ] ?? $default;
	}

	/**
	 * Check if session has a key
	 */
	public function has( string $key ): bool
	{
		$this->start();
		return isset( $_SESSION[ $key ] );
	}

	/**
	 * Remove a session value
	 */
	public function remove( string $key ): void
	{
		$this->start();
		unset( $_SESSION[ $key ] );
	}

	/**
	 * Set a flash message (available for next request only)
	 */
	public function flash( string $key, mixed $value ): void
	{
		$this->start();
		$_SESSION['_flash'][ $key ] = $value;
	}

	/**
	 * Get a flash message
	 */
	public function getFlash( string $key, mixed $default = null ): mixed
	{
		$this->start();
		$value = $_SESSION['_flash'][ $key ] ?? $default;
		unset( $_SESSION['_flash'][ $key ] );
		return $value;
	}

	/**
	 * Check if flash message exists
	 */
	public function hasFlash( string $key ): bool
	{
		$this->start();
		return isset( $_SESSION['_flash'][ $key ] );
	}

	/**
	 * Get all flash messages and clear them
	 *
	 * @return array<string, mixed>
	 */
	public function getAllFlash(): array
	{
		$this->start();
		$flash = $_SESSION['_flash'] ?? [];
		unset( $_SESSION['_flash'] );
		return $flash;
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
		return $this->_started || session_status() === PHP_SESSION_ACTIVE;
	}
}
