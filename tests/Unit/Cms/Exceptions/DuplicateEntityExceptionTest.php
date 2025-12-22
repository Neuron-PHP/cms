<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\DuplicateEntityException;
use PHPUnit\Framework\TestCase;

class DuplicateEntityExceptionTest extends TestCase
{
	public function test_creates_exception_with_entity_type_field_and_value(): void
	{
		$exception = new DuplicateEntityException( 'User', 'username', 'john_doe' );

		$this->assertStringContainsString( 'User', $exception->getMessage() );
		$this->assertStringContainsString( 'username', $exception->getMessage() );
		$this->assertStringContainsString( 'john_doe', $exception->getMessage() );
	}

	public function test_technical_message_format(): void
	{
		$exception = new DuplicateEntityException( 'User', 'email', 'test@example.com' );

		$this->assertEquals(
			"Duplicate User: email 'test@example.com' already exists",
			$exception->getMessage()
		);
	}

	public function test_user_message_format(): void
	{
		$exception = new DuplicateEntityException( 'User', 'username', 'john_doe' );

		$this->assertEquals(
			"Username 'john_doe' is already in use",
			$exception->getUserMessage()
		);
	}

	public function test_user_message_capitalizes_field_name(): void
	{
		$exception = new DuplicateEntityException( 'Post', 'slug', 'my-post' );

		$this->assertStringStartsWith( 'Slug', $exception->getUserMessage() );
	}

	public function test_should_not_log_by_default(): void
	{
		$exception = new DuplicateEntityException( 'User', 'username', 'john' );

		$this->assertFalse( $exception->shouldLog() );
	}

	public function test_includes_error_code(): void
	{
		$exception = new DuplicateEntityException( 'User', 'username', 'john', 409 );

		$this->assertEquals( 409, $exception->getCode() );
	}
}
