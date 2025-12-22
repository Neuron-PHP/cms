<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown when attempting to create a duplicate entity
 *
 * @package Neuron\Cms\Exceptions
 */
class DuplicateEntityException extends CmsException
{
	/**
	 * @param string $entityType Type of entity (e.g., "User", "Category")
	 * @param string $field Duplicate field name (e.g., "username", "slug")
	 * @param string $value The duplicate value
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string $entityType,
		string $field,
		string $value,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$message = "Duplicate {$entityType}: {$field} '{$value}' already exists";
		$userMessage = ucfirst( $field ) . " '{$value}' is already in use";

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Duplicate attempts don't need logging
	 */
	public function shouldLog(): bool
	{
		return false;
	}
}
