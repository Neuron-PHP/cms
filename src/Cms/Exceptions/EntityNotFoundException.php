<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown when an entity cannot be found
 *
 * @package Neuron\Cms\Exceptions
 */
class EntityNotFoundException extends CmsException
{
	/**
	 * @param string $entityType Type of entity (e.g., "User", "Post")
	 * @param int|string $identifier Entity identifier (ID, slug, etc.)
	 * @param string $identifierType Type of identifier (e.g., "ID", "slug")
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $entityType,
		int|string $identifier,
		string $identifierType = 'ID',
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$message = "{$entityType} not found: {$identifierType} {$identifier}";
		$userMessage = "{$entityType} not found";

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Not found errors should be logged for security monitoring
	 */
	public function shouldLog(): bool
	{
		return true;
	}
}
