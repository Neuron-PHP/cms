<?php

namespace Tests\Unit\Cms\Exceptions;

use Neuron\Cms\Exceptions\CmsException;
use PHPUnit\Framework\TestCase;

class CmsExceptionTest extends TestCase
{
	public function test_creates_exception_with_technical_message(): void
	{
		$exception = new CmsException( 'Technical error message' );

		$this->assertEquals( 'Technical error message', $exception->getMessage() );
	}

	public function test_creates_exception_with_user_message(): void
	{
		$exception = new CmsException(
			'Technical error message',
			'User-friendly message'
		);

		$this->assertEquals( 'User-friendly message', $exception->getUserMessage() );
	}

	public function test_user_message_defaults_to_technical_message(): void
	{
		$exception = new CmsException( 'Technical error message' );

		$this->assertEquals( 'Technical error message', $exception->getUserMessage() );
	}

	public function test_includes_error_code(): void
	{
		$exception = new CmsException( 'Error', null, 404 );

		$this->assertEquals( 404, $exception->getCode() );
	}

	public function test_includes_previous_exception(): void
	{
		$previous = new \Exception( 'Previous error' );
		$exception = new CmsException( 'Current error', null, 0, $previous );

		$this->assertSame( $previous, $exception->getPrevious() );
	}

	public function test_should_log_returns_true_by_default(): void
	{
		$exception = new CmsException( 'Error' );

		$this->assertTrue( $exception->shouldLog() );
	}
}
