<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown when authorization fails
 *
 * @package Neuron\Cms\Exceptions
 */
class AuthorizationException extends CmsException
{
	/**
	 * @param string $action Action being attempted
	 * @param string|null $resource Resource being accessed
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $action,
		?string $resource = null,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$message = $resource
			? "Unauthorized to {$action} {$resource}"
			: "Unauthorized to {$action}";

		$userMessage = "You don't have permission to perform this action";

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Authorization failures should always be logged
	 */
	public function shouldLog(): bool
	{
		return true;
	}
}
