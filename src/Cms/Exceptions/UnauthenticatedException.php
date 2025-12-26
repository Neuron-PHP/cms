<?php

namespace Neuron\Cms\Exceptions;

use RuntimeException;

/**
 * Exception thrown when user is not authenticated but authentication is required.
 *
 * This exception should be caught at the application/framework level
 * and handled appropriately (typically by redirecting to login page).
 *
 * @package Neuron\Cms\Exceptions
 */
class UnauthenticatedException extends RuntimeException
{
	private string $_redirectUrl;
	private string $_intendedUrl;

	/**
	 * @param string $redirectUrl URL to redirect to (typically login page)
	 * @param string $intendedUrl Original URL user was trying to access
	 * @param string $message Technical message for logging
	 */
	public function __construct( string $redirectUrl, string $intendedUrl, string $message = 'Authentication required' )
	{
		parent::__construct( $message, 401 );
		$this->_redirectUrl = $redirectUrl;
		$this->_intendedUrl = $intendedUrl;
	}

	/**
	 * Get URL to redirect to (login page)
	 */
	public function getRedirectUrl(): string
	{
		return $this->_redirectUrl;
	}

	/**
	 * Get original URL user was trying to access
	 */
	public function getIntendedUrl(): string
	{
		return $this->_intendedUrl;
	}
}
