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
	private SessionManager $_SessionManager;
	private string $_TokenKey = 'csrf_token';

	public function __construct( SessionManager $SessionManager )
	{
		$this->_SessionManager = $SessionManager;
	}

	/**
	 * Generate a new CSRF token
	 */
	public function generate(): string
	{
		$Token = bin2hex( random_bytes( 32 ) );
		$this->_SessionManager->set( $this->_TokenKey, $Token );
		return $Token;
	}

	/**
	 * Get the current CSRF token (generate if doesn't exist)
	 */
	public function getToken(): string
	{
		if( !$this->_SessionManager->has( $this->_TokenKey ) )
		{
			return $this->generate();
		}

		return $this->_SessionManager->get( $this->_TokenKey );
	}

	/**
	 * Validate a CSRF token
	 */
	public function validate( string $Token ): bool
	{
		$StoredToken = $this->_SessionManager->get( $this->_TokenKey );

		if( !$StoredToken )
		{
			return false;
		}

		// Use hash_equals to prevent timing attacks
		return hash_equals( $StoredToken, $Token );
	}

	/**
	 * Regenerate CSRF token
	 */
	public function regenerate(): string
	{
		return $this->generate();
	}
}
