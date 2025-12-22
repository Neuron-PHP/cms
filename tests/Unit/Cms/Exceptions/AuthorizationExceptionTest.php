<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\AuthorizationException;
use PHPUnit\Framework\TestCase;

class AuthorizationExceptionTest extends TestCase
{
	public function test_creates_exception_with_action_and_resource(): void
	{
		$exception = new AuthorizationException( 'edit', 'this post' );

		$this->assertEquals(
			'Unauthorized to edit this post',
			$exception->getMessage()
		);
	}

	public function test_creates_exception_with_action_only(): void
	{
		$exception = new AuthorizationException( 'access admin panel' );

		$this->assertEquals(
			'Unauthorized to access admin panel',
			$exception->getMessage()
		);
	}

	public function test_user_message_is_generic(): void
	{
		$exception = new AuthorizationException( 'delete', 'user account' );

		$this->assertEquals(
			"You don't have permission to perform this action",
			$exception->getUserMessage()
		);
	}

	public function test_user_message_does_not_expose_details(): void
	{
		$exception = new AuthorizationException( 'view', 'secret document' );

		$this->assertStringNotContainsString( 'secret', $exception->getUserMessage() );
	}

	public function test_should_log_by_default(): void
	{
		$exception = new AuthorizationException( 'delete', 'post' );

		$this->assertTrue( $exception->shouldLog() );
	}
}
