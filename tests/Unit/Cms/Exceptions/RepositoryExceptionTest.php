<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\RepositoryException;
use PHPUnit\Framework\TestCase;

class RepositoryExceptionTest extends TestCase
{
	public function test_creates_exception_with_operation_and_entity_type(): void
	{
		$exception = new RepositoryException( 'save', 'User' );

		$this->assertEquals(
			'Failed to save User',
			$exception->getMessage()
		);
	}

	public function test_includes_details_in_message(): void
	{
		$exception = new RepositoryException(
			'delete',
			'Post',
			'Foreign key constraint violation'
		);

		$this->assertEquals(
			'Failed to delete Post: Foreign key constraint violation',
			$exception->getMessage()
		);
	}

	public function test_user_message_is_generic(): void
	{
		$exception = new RepositoryException( 'save', 'User', 'Database error' );

		$this->assertEquals(
			'An error occurred while processing your request. Please try again.',
			$exception->getUserMessage()
		);
	}

	public function test_user_message_does_not_expose_technical_details(): void
	{
		$exception = new RepositoryException(
			'save',
			'User',
			'SQL syntax error at line 42'
		);

		$this->assertStringNotContainsString( 'SQL', $exception->getUserMessage() );
		$this->assertStringNotContainsString( '42', $exception->getUserMessage() );
	}

	public function test_should_log_by_default(): void
	{
		$exception = new RepositoryException( 'save', 'User' );

		$this->assertTrue( $exception->shouldLog() );
	}
}
