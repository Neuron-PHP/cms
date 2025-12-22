<?php

namespace Tests\Unit\Cms\Models;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\Event;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class EventCategoryTest extends TestCase
{
	public function test_constructor_sets_created_at(): void
	{
		$category = new EventCategory();

		$this->assertInstanceOf( DateTimeImmutable::class, $category->getCreatedAt() );
	}

	public function test_get_and_set_id(): void
	{
		$category = new EventCategory();

		$this->assertNull( $category->getId() );

		$category->setId( 42 );

		$this->assertEquals( 42, $category->getId() );
	}

	public function test_get_and_set_name(): void
	{
		$category = new EventCategory();

		$category->setName( 'Conferences' );

		$this->assertEquals( 'Conferences', $category->getName() );
	}

	public function test_get_and_set_slug(): void
	{
		$category = new EventCategory();

		$category->setSlug( 'conferences' );

		$this->assertEquals( 'conferences', $category->getSlug() );
	}

	public function test_get_color_defaults_to_blue(): void
	{
		$category = new EventCategory();

		$this->assertEquals( '#3b82f6', $category->getColor() );
	}

	public function test_set_color(): void
	{
		$category = new EventCategory();

		$category->setColor( '#ff0000' );

		$this->assertEquals( '#ff0000', $category->getColor() );
	}

	public function test_get_and_set_description(): void
	{
		$category = new EventCategory();

		$this->assertNull( $category->getDescription() );

		$category->setDescription( 'Professional conferences and workshops' );

		$this->assertEquals( 'Professional conferences and workshops', $category->getDescription() );
	}

	public function test_set_description_null(): void
	{
		$category = new EventCategory();

		$category->setDescription( 'Test description' );
		$category->setDescription( null );

		$this->assertNull( $category->getDescription() );
	}

	public function test_get_and_set_updated_at(): void
	{
		$category = new EventCategory();

		$this->assertNull( $category->getUpdatedAt() );

		$updatedAt = new DateTimeImmutable( '2025-01-15 12:00:00' );
		$category->setUpdatedAt( $updatedAt );

		$this->assertSame( $updatedAt, $category->getUpdatedAt() );
	}

	public function test_set_updated_at_null(): void
	{
		$category = new EventCategory();

		$category->setUpdatedAt( new DateTimeImmutable() );
		$category->setUpdatedAt( null );

		$this->assertNull( $category->getUpdatedAt() );
	}

	public function test_get_events_defaults_to_empty_array(): void
	{
		$category = new EventCategory();

		$events = $category->getEvents();

		$this->assertIsArray( $events );
		$this->assertEmpty( $events );
	}

	public function test_set_events(): void
	{
		$category = new EventCategory();

		$event1 = new Event();
		$event1->setId( 1 );
		$event1->setTitle( 'Event 1' );

		$event2 = new Event();
		$event2->setId( 2 );
		$event2->setTitle( 'Event 2' );

		$events = [$event1, $event2];
		$category->setEvents( $events );

		$this->assertSame( $events, $category->getEvents() );
		$this->assertCount( 2, $category->getEvents() );
	}

	public function test_from_array_creates_category_with_all_fields(): void
	{
		$data = [
			'id' => 10,
			'name' => 'Workshops',
			'slug' => 'workshops',
			'color' => '#00ff00',
			'description' => 'Hands-on learning sessions',
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-15 15:00:00'
		];

		$category = EventCategory::fromArray( $data );

		$this->assertEquals( 10, $category->getId() );
		$this->assertEquals( 'Workshops', $category->getName() );
		$this->assertEquals( 'workshops', $category->getSlug() );
		$this->assertEquals( '#00ff00', $category->getColor() );
		$this->assertEquals( 'Hands-on learning sessions', $category->getDescription() );
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getCreatedAt() );
		$this->assertEquals( '2025-01-01 10:00:00', $category->getCreatedAt()->format( 'Y-m-d H:i:s' ) );
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getUpdatedAt() );
		$this->assertEquals( '2025-01-15 15:00:00', $category->getUpdatedAt()->format( 'Y-m-d H:i:s' ) );
	}

	public function test_from_array_with_minimal_data(): void
	{
		$data = [
			'name' => 'Meetups',
			'slug' => 'meetups'
		];

		$category = EventCategory::fromArray( $data );

		$this->assertNull( $category->getId() );
		$this->assertEquals( 'Meetups', $category->getName() );
		$this->assertEquals( 'meetups', $category->getSlug() );
		$this->assertEquals( '#3b82f6', $category->getColor() );
		$this->assertNull( $category->getDescription() );
	}

	public function test_from_array_with_datetime_objects(): void
	{
		$createdAt = new DateTimeImmutable( '2025-01-01 10:00:00' );
		$updatedAt = new DateTimeImmutable( '2025-01-15 15:00:00' );

		$data = [
			'name' => 'Test',
			'slug' => 'test',
			'created_at' => $createdAt,
			'updated_at' => $updatedAt
		];

		$category = EventCategory::fromArray( $data );

		$this->assertSame( $createdAt, $category->getCreatedAt() );
		$this->assertSame( $updatedAt, $category->getUpdatedAt() );
	}

	public function test_from_array_handles_empty_timestamps(): void
	{
		$data = [
			'name' => 'Test',
			'slug' => 'test',
			'created_at' => '',
			'updated_at' => ''
		];

		$category = EventCategory::fromArray( $data );

		// Timestamps should not be set from empty strings
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getCreatedAt() );
		$this->assertNull( $category->getUpdatedAt() );
	}

	public function test_to_array_includes_all_fields(): void
	{
		$category = new EventCategory();
		$category->setId( 25 );
		$category->setName( 'Conferences' );
		$category->setSlug( 'conferences' );
		$category->setColor( '#ff00ff' );
		$category->setDescription( 'Professional events' );
		$category->setCreatedAt( new DateTimeImmutable( '2025-01-01 10:00:00' ) );
		$category->setUpdatedAt( new DateTimeImmutable( '2025-01-15 12:00:00' ) );

		$array = $category->toArray();

		$this->assertEquals( 25, $array['id'] );
		$this->assertEquals( 'Conferences', $array['name'] );
		$this->assertEquals( 'conferences', $array['slug'] );
		$this->assertEquals( '#ff00ff', $array['color'] );
		$this->assertEquals( 'Professional events', $array['description'] );
		$this->assertEquals( '2025-01-01 10:00:00', $array['created_at'] );
		$this->assertEquals( '2025-01-15 12:00:00', $array['updated_at'] );
	}

	public function test_to_array_handles_null_fields(): void
	{
		$category = new EventCategory();
		$category->setName( 'Test' );
		$category->setSlug( 'test' );

		$array = $category->toArray();

		$this->assertNull( $array['id'] );
		$this->assertNull( $array['description'] );
		$this->assertNull( $array['updated_at'] );
		$this->assertNotNull( $array['created_at'] ); // Constructor sets this
	}

	public function test_setter_methods_return_self_for_chaining(): void
	{
		$category = new EventCategory();

		$result = $category
			->setId( 1 )
			->setName( 'Test' )
			->setSlug( 'test' )
			->setColor( '#000000' )
			->setDescription( 'Description' )
			->setCreatedAt( new DateTimeImmutable() )
			->setUpdatedAt( new DateTimeImmutable() )
			->setEvents( [] );

		$this->assertSame( $category, $result );
	}
}
