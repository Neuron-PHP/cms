<?php

namespace Neuron\Cms\Auth;

/**
 * CSRF token manager.
 *
 * Generates and validates CSRF tokens to prevent
 * Cross-Site Request Forgery attacks.
 *
 * @package Neuron\Cms\Auth
 */
class CsrfTokenManager
{
	private SessionManager $_sessionManager;
	private string $_tokenKey = 'csrf_token';

	public function __construct( SessionManager $sessionManager )
	{
		$this->_sessionManager = $sessionManager;
	}

	/**
	 * Generate a new CSRF token
	 */
	public function generate(): string
	{
		$token = bin2hex( random_bytes( 32 ) );
		$this->_sessionManager->set( $this->_tokenKey, $token );
		return $token;
	}

	/**
	 * Get the current CSRF token (generate if doesn't exist)
	 */
	public function getToken(): string
	{
		if( !$this->_sessionManager->has( $this->_tokenKey ) )
		{
			return $this->generate();
		}

		return $this->_sessionManager->get( $this->_tokenKey );
	}

	/**
	 * Validate a CSRF token
	 */
	public function validate( string $token ): bool
	{
		$storedToken = $this->_sessionManager->get( $this->_tokenKey );

		if( !$storedToken )
		{
			return false;
		}

		// Use hash_equals to prevent timing attacks
		return hash_equals( $storedToken, $token );
	}

	/**
	 * Regenerate CSRF token
	 */
	public function regenerate(): string
	{
		return $this->generate();
	}
}
