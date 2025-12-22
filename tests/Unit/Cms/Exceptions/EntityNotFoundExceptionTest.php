<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\EntityNotFoundException;
use PHPUnit\Framework\TestCase;

class EntityNotFoundExceptionTest extends TestCase
{
	public function test_creates_exception_with_entity_type_and_id(): void
	{
		$exception = new EntityNotFoundException( 'User', 123 );

		$this->assertStringContainsString( 'User', $exception->getMessage() );
		$this->assertStringContainsString( '123', $exception->getMessage() );
	}

	public function test_technical_message_with_id(): void
	{
		$exception = new EntityNotFoundException( 'Post', 456 );

		$this->assertEquals(
			'Post not found: ID 456',
			$exception->getMessage()
		);
	}

	public function test_technical_message_with_custom_identifier_type(): void
	{
		$exception = new EntityNotFoundException( 'Post', 'my-slug', 'slug' );

		$this->assertEquals(
			'Post not found: slug my-slug',
			$exception->getMessage()
		);
	}

	public function test_user_message_is_generic(): void
	{
		$exception = new EntityNotFoundException( 'User', 123 );

		$this->assertEquals(
			'User not found',
			$exception->getUserMessage()
		);
	}

	public function test_user_message_does_not_expose_identifier(): void
	{
		$exception = new EntityNotFoundException( 'User', 123 );

		$this->assertStringNotContainsString( '123', $exception->getUserMessage() );
	}

	public function test_supports_string_identifier(): void
	{
		$exception = new EntityNotFoundException( 'Category', 'news', 'slug' );

		$this->assertStringContainsString( 'news', $exception->getMessage() );
	}

	public function test_should_log_by_default(): void
	{
		$exception = new EntityNotFoundException( 'User', 123 );

		$this->assertTrue( $exception->shouldLog() );
	}
}
