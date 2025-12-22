<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown for security-related errors
 *
 * @package Neuron\Cms\Exceptions
 */
class SecurityException extends CmsException
{
	/**
	 * @param string $reason Security violation reason
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $reason,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$message = "Security violation: {$reason}";
		$userMessage = "Invalid security token. Please try again.";

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Security violations should always be logged
	 */
	public function shouldLog(): bool
	{
		return true;
	}
}
