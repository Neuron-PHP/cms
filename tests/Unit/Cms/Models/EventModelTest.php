<?php

namespace Tests\Unit\Cms\Models;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\User;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class EventModelTest extends TestCase
{
	public function test_set_creator_sets_created_by_when_creator_has_id(): void
	{
		$user = new User();
		$user->setId( 123 );

		$event = new Event();
		$event->setCreator( $user );

		$this->assertEquals( 123, $event->getCreatedBy() );
	}

	public function test_set_creator_sets_created_by_to_null_when_creator_is_null(): void
	{
		$event = new Event();
		$event->setCreator( null );

		$this->assertNull( $event->getCreatedBy() );
	}

	public function test_set_creator_sets_created_by_to_null_when_creator_has_no_id(): void
	{
		$user = new User();
		// User has no ID set

		$event = new Event();
		$event->setCreator( $user );

		$this->assertNull( $event->getCreatedBy() );
	}

	public function test_set_creator_clears_previous_created_by_when_set_to_null(): void
	{
		$user = new User();
		$user->setId( 456 );

		$event = new Event();
		$event->setCreator( $user );

		$this->assertEquals( 456, $event->getCreatedBy() );

		// Now set to null
		$event->setCreator( null );

		$this->assertNull( $event->getCreatedBy() );
	}

	public function test_to_array_includes_start_date_when_set(): void
	{
		$startDate = new DateTimeImmutable( '2025-01-15 10:00:00' );

		$event = new Event();
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		$event->setStartDate( $startDate );
		$event->setStatus( 'published' );

		$array = $event->toArray();

		$this->assertEquals( '2025-01-15 10:00:00', $array['start_date'] );
	}

	public function test_to_array_returns_null_for_start_date_when_not_set(): void
	{
		$event = new Event();
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		$event->setStatus( 'published' );
		// Don't set start_date

		$array = $event->toArray();

		$this->assertArrayHasKey( 'start_date', $array );
		$this->assertNull( $array['start_date'] );
	}

	public function test_to_array_excludes_id_when_null(): void
	{
		$event = new Event();
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		// ID is null

		$array = $event->toArray();

		$this->assertArrayNotHasKey( 'id', $array );
	}

	public function test_to_array_includes_id_when_set(): void
	{
		$event = new Event();
		$event->setId( 789 );
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );

		$array = $event->toArray();

		$this->assertArrayHasKey( 'id', $array );
		$this->assertEquals( 789, $array['id'] );
	}

	public function test_to_array_includes_nullable_created_by(): void
	{
		$event = new Event();
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		$event->setCreator( null );

		$array = $event->toArray();

		$this->assertArrayHasKey( 'created_by', $array );
		$this->assertNull( $array['created_by'] );
	}

	public function test_to_array_includes_created_by_when_set(): void
	{
		$user = new User();
		$user->setId( 999 );

		$event = new Event();
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		$event->setCreator( $user );

		$array = $event->toArray();

		$this->assertEquals( 999, $array['created_by'] );
	}
}
