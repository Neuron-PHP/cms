<?php

namespace Neuron\Cms\Exceptions;

/**
 * Exception thrown when validation fails
 *
 * @package Neuron\Cms\Exceptions
 */
class ValidationException extends CmsException
{
	/** @var array<string> */
	private array $errors = [];

	/**
	 * @param string|array<string> $errors Validation error(s)
	 * @param int $code Error code
	 * @param \Throwable|null $previous Previous exception
	 */
	public function __construct(
		string|array $errors,
		int $code = 0,
		?\Throwable $previous = null
	)
	{
		$this->errors = is_array( $errors ) ? $errors : [ $errors ];

		$message = 'Validation failed: ' . implode( ', ', $this->errors );
		$userMessage = count( $this->errors ) === 1
			? $this->errors[0]
			: 'Please correct the following errors: ' . implode( ', ', $this->errors );

		parent::__construct( $message, $userMessage, $code, $previous );
	}

	/**
	 * Get all validation errors
	 *
	 * @return array<string>
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Validation errors don't need to be logged by default
	 */
	public function shouldLog(): bool
	{
		return false;
	}
}
