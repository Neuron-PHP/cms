<?php

namespace Neuron\Cms\Exceptions;

use RuntimeException;

/**
 * Base exception for all CMS-specific exceptions
 *
 * @package Neuron\Cms\Exceptions
 */
class CmsException extends RuntimeException
{
	protected string $userMessage;

	/**
	 * @param string $message Technical error message (for logs)
	 * @param string|null $userMessage User-friendly error message (for display)
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $message,
		?string $userMessage = null,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		parent::__construct( $message, $code, $previous );
		$this->userMessage = $userMessage ?? $message;
	}

	/**
	 * Get user-friendly error message safe for display
	 */
	public function getUserMessage(): string
	{
		return $this->userMessage;
	}

	/**
	 * Check if this exception should be logged
	 */
	public function shouldLog(): bool
	{
		return true;
	}
}
