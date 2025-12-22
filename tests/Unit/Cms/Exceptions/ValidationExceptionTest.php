<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
	public function test_creates_exception_with_single_error(): void
	{
		$exception = new ValidationException( 'Email is required' );

		$this->assertCount( 1, $exception->getErrors() );
		$this->assertEquals( ['Email is required'], $exception->getErrors() );
	}

	public function test_creates_exception_with_multiple_errors(): void
	{
		$errors = [
			'Email is required',
			'Password must be at least 8 characters'
		];

		$exception = new ValidationException( $errors );

		$this->assertCount( 2, $exception->getErrors() );
		$this->assertEquals( $errors, $exception->getErrors() );
	}

	public function test_technical_message_includes_all_errors(): void
	{
		$errors = ['Error 1', 'Error 2'];
		$exception = new ValidationException( $errors );

		$this->assertStringContainsString( 'Validation failed', $exception->getMessage() );
		$this->assertStringContainsString( 'Error 1', $exception->getMessage() );
		$this->assertStringContainsString( 'Error 2', $exception->getMessage() );
	}

	public function test_user_message_for_single_error_returns_error_directly(): void
	{
		$exception = new ValidationException( 'Email is required' );

		$this->assertEquals( 'Email is required', $exception->getUserMessage() );
	}

	public function test_user_message_for_multiple_errors_includes_prompt(): void
	{
		$errors = ['Error 1', 'Error 2'];
		$exception = new ValidationException( $errors );

		$userMessage = $exception->getUserMessage();

		$this->assertStringContainsString( 'Please correct the following errors', $userMessage );
		$this->assertStringContainsString( 'Error 1', $userMessage );
		$this->assertStringContainsString( 'Error 2', $userMessage );
	}

	public function test_should_not_log_by_default(): void
	{
		$exception = new ValidationException( 'Error' );

		$this->assertFalse( $exception->shouldLog() );
	}
}
