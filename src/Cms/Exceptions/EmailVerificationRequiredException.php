<?php

namespace Neuron\Cms\Exceptions;

use RuntimeException;

/**
 * Exception thrown when email verification is required but not completed.
 *
 * This exception should be caught at the application/framework level
 * and handled appropriately (typically by redirecting to verification page).
 *
 * @package Neuron\Cms\Exceptions
 */
class EmailVerificationRequiredException extends RuntimeException
{
	private string $_verificationUrl;

	/**
	 * @param string $verificationUrl URL to redirect to (verification page)
	 * @param string $message Technical message for logging
	 */
	public function __construct( string $verificationUrl, string $message = 'Email verification required' )
	{
		parent::__construct( $message, 403 );
		$this->_verificationUrl = $verificationUrl;
	}

	/**
	 * Get URL to redirect to (verification page)
	 */
	public function getVerificationUrl(): string
	{
		return $this->_verificationUrl;
	}
}
