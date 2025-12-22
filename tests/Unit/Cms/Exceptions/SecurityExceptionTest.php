<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\SecurityException;
use PHPUnit\Framework\TestCase;

class SecurityExceptionTest extends TestCase
{
	public function test_creates_exception_with_reason(): void
	{
		$exception = new SecurityException( 'CSRF token validation failed' );

		$this->assertEquals(
			'Security violation: CSRF token validation failed',
			$exception->getMessage()
		);
	}

	public function test_user_message_is_generic(): void
	{
		$exception = new SecurityException( 'CSRF token validation failed' );

		$this->assertEquals(
			'Invalid security token. Please try again.',
			$exception->getUserMessage()
		);
	}

	public function test_user_message_does_not_expose_details(): void
	{
		$exception = new SecurityException( 'SQL injection attempt detected' );

		$this->assertStringNotContainsString( 'SQL', $exception->getUserMessage() );
		$this->assertStringNotContainsString( 'injection', $exception->getUserMessage() );
	}

	public function test_should_log_by_default(): void
	{
		$exception = new SecurityException( 'XSS attempt' );

		$this->assertTrue( $exception->shouldLog() );
	}
}
