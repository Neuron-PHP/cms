<?php

namespace Neuron\Cms\Exceptions;

use RuntimeException;

/**
 * Exception thrown when CSRF token validation fails.
 *
 * This exception should be caught at the application/framework level
 * and handled appropriately (typically by returning a 403 response).
 *
 * @package Neuron\Cms\Exceptions
 */
class CsrfValidationException extends RuntimeException
{
	private string $_userMessage;

	/**
	 * @param string $message Technical message for logging
	 * @param string $userMessage User-friendly message to display
	 */
	public function __construct( string $message, string $userMessage = 'CSRF token validation failed' )
	{
		parent::__construct( $message, 403 );
		$this->_userMessage = $userMessage;
	}

	/**
	 * Get user-friendly message suitable for display
	 */
	public function getUserMessage(): string
	{
		return $this->_userMessage;
	}
}
