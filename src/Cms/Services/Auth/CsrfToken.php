<?php

namespace Neuron\Cms\Services\Auth;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Core\System\IRandom;
use Neuron\Core\System\RealRandom;

/**
 * CSRF token service.
 *
 * Generates and validates CSRF tokens to prevent
 * Cross-Site Request Forgery attacks.
 *
 * @package Neuron\Cms\Services\Auth
 */
class CsrfToken
{
	private SessionManager $_sessionManager;
	private string $_tokenKey = 'csrf_token';
	private IRandom $random;

	public function __construct( SessionManager $sessionManager, ?IRandom $random = null )
	{
		$this->_sessionManager = $sessionManager;
		$this->random = $random ?? new RealRandom();
	}

	/**
	 * Generate a new CSRF token
	 */
	public function generate(): string
	{
		$token = $this->random->string( 64, 'hex' );
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
