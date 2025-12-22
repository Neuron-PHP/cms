<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown when repository operations fail
 *
 * @package Neuron\Cms\Exceptions
 */
class RepositoryException extends CmsException
{
	/**
	 * @param string $operation Operation that failed (e.g., "save", "delete")
	 * @param string $entityType Type of entity
	 * @param string|null $details Additional details about the failure
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $operation,
		string $entityType,
		?string $details = null,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$message = "Failed to {$operation} {$entityType}";
		if( $details )
		{
			$message .= ": {$details}";
		}

		$userMessage = "An error occurred while processing your request. Please try again.";

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Repository errors should always be logged
	 */
	public function shouldLog(): bool
	{
		return true;
	}
}
