<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Controllers\Content;
use Neuron\Dto\Factory;
use Neuron\Mvc\Requests\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Regression tests for Content::mapRequestToDto() numeric casting.
 *
 * HTML form fields always arrive as strings, but the DTO integer/float type
 * validators require real int/float values. The mapper must cast them.
 */
class ContentMapRequestTest extends TestCase
{
	private function mapper(): ReflectionMethod
	{
		return new ReflectionMethod( Content::class, 'mapRequestToDto' );
	}

	private function content(): Content
	{
		// Avoid the heavy constructor; the method under test is stateless.
		return ( new ReflectionClass( Content::class ) )->newInstanceWithoutConstructor();
	}

	private function eventDto()
	{
		$factory = new Factory( __DIR__ . '/../../../../src/Cms/Dtos/events/create-event-request.yaml' );
		return $factory->create();
	}

	private function requestReturning( array $values ): Request
	{
		$request = $this->createMock( Request::class );
		$request->method( 'post' )->willReturnCallback(
			fn( $name, $default = null ) => $values[ $name ] ?? $default
		);
		return $request;
	}

	public function testIntegerStringIsCastToInt(): void
	{
		$dto = $this->eventDto();
		$request = $this->requestReturning( [
			'title'      => 'Fundraiser',
			'content'    => '{"blocks":[]}',
			'start_date' => '2026-07-01 18:00:00',
			'status'     => 'published',
			'category_id' => '5',
		] );

		$this->mapper()->invoke( $this->content(), $dto, $request );

		$this->assertSame( 5, $dto->category_id );
	}

	public function testCastedIntegerPassesValidation(): void
	{
		$dto = $this->eventDto();
		$request = $this->requestReturning( [
			'title'      => 'Fundraiser',
			'content'    => '{"blocks":[]}',
			'start_date' => '2026-07-01 18:00:00',
			'status'     => 'published',
			'category_id' => '5',
		] );

		$this->mapper()->invoke( $this->content(), $dto, $request );
		$dto->created_by = 1;

		$this->assertTrue( $dto->validate(), 'DTO should validate with a numeric category_id' );
	}

	public function testEmptyIntegerIsLeftUnset(): void
	{
		$dto = $this->eventDto();
		$request = $this->requestReturning( [
			'title'      => 'Fundraiser',
			'content'    => '{"blocks":[]}',
			'start_date' => '2026-07-01 18:00:00',
			'status'     => 'published',
			'category_id' => '',
		] );

		$this->mapper()->invoke( $this->content(), $dto, $request );
		$dto->created_by = 1;

		$this->assertNull( $dto->category_id );
		$this->assertTrue( $dto->validate(), 'Optional empty category_id should not fail validation' );
	}

	public function testBooleanCheckboxStillMaps(): void
	{
		$dto = $this->eventDto();
		$request = $this->requestReturning( [
			'title'      => 'Fundraiser',
			'content'    => '{"blocks":[]}',
			'start_date' => '2026-07-01 18:00:00',
			'status'     => 'published',
			'all_day'    => 'on',
		] );

		$this->mapper()->invoke( $this->content(), $dto, $request );

		$this->assertTrue( $dto->all_day );
	}
}
