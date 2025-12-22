<?php

namespace Tests\Unit\Cms\Models;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\User;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use JsonException;

class EventTest extends TestCase
{
	public function test_constructor_sets_created_at(): void
	{
		$event = new Event();

		$this->assertInstanceOf( DateTimeImmutable::class, $event->getCreatedAt() );
	}

	public function test_get_and_set_id(): void
	{
		$event = new Event();

		$this->assertNull( $event->getId() );

		$event->setId( 123 );

		$this->assertEquals( 123, $event->getId() );
	}

	public function test_get_and_set_title(): void
	{
		$event = new Event();

		$event->setTitle( 'Tech Conference 2025' );

		$this->assertEquals( 'Tech Conference 2025', $event->getTitle() );
	}

	public function test_get_and_set_slug(): void
	{
		$event = new Event();

		$event->setSlug( 'tech-conference-2025' );

		$this->assertEquals( 'tech-conference-2025', $event->getSlug() );
	}

	public function test_get_and_set_description(): void
	{
		$event = new Event();

		$this->assertNull( $event->getDescription() );

		$event->setDescription( 'An amazing tech conference' );

		$this->assertEquals( 'An amazing tech conference', $event->getDescription() );
	}

	public function test_get_content_returns_array(): void
	{
		$event = new Event();

		$content = $event->getContent();

		$this->assertIsArray( $content );
		$this->assertArrayHasKey( 'blocks', $content );
	}

	public function test_get_content_raw_returns_default_json(): void
	{
		$event = new Event();

		$raw = $event->getContentRaw();

		$this->assertEquals( '{"blocks":[]}', $raw );
	}

	public function test_set_content_from_json_string(): void
	{
		$event = new Event();

		$json = '{"blocks":[{"type":"paragraph","data":{"text":"Hello"}}]}';
		$event->setContent( $json );

		$this->assertEquals( $json, $event->getContentRaw() );
		$this->assertIsArray( $event->getContent() );
		$this->assertCount( 1, $event->getContent()['blocks'] );
	}

	public function test_set_content_array(): void
	{
		$event = new Event();

		$content = [
			'blocks' => [
				['type' => 'paragraph', 'data' => ['text' => 'Hello World']]
			]
		];

		$event->setContentArray( $content );

		$decoded = json_decode( $event->getContentRaw(), true );
		$this->assertEquals( $content, $decoded );
	}

	public function test_set_content_array_throws_exception_on_invalid_json(): void
	{
		$event = new Event();

		// Create an invalid structure that can't be JSON encoded
		$invalidContent = ['blocks' => ["\xB1\x31"]]; // Invalid UTF-8

		$this->expectException( JsonException::class );
		$this->expectExceptionMessage( 'Failed to encode content array to JSON' );

		$event->setContentArray( $invalidContent );
	}

	public function test_get_and_set_location(): void
	{
		$event = new Event();

		$this->assertNull( $event->getLocation() );

		$event->setLocation( 'Convention Center, 123 Main St' );

		$this->assertEquals( 'Convention Center, 123 Main St', $event->getLocation() );
	}

	public function test_get_and_set_start_date(): void
	{
		$event = new Event();

		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );
		$event->setStartDate( $startDate );

		$this->assertSame( $startDate, $event->getStartDate() );
	}

	public function test_get_and_set_end_date(): void
	{
		$event = new Event();

		$this->assertNull( $event->getEndDate() );

		$endDate = new DateTimeImmutable( '2025-06-15 18:00:00' );
		$event->setEndDate( $endDate );

		$this->assertSame( $endDate, $event->getEndDate() );
	}

	public function test_is_all_day_defaults_to_false(): void
	{
		$event = new Event();

		$this->assertFalse( $event->isAllDay() );
	}

	public function test_set_all_day(): void
	{
		$event = new Event();

		$event->setAllDay( true );

		$this->assertTrue( $event->isAllDay() );
	}

	public function test_get_and_set_category_id(): void
	{
		$event = new Event();

		$this->assertNull( $event->getCategoryId() );

		$event->setCategoryId( 5 );

		$this->assertEquals( 5, $event->getCategoryId() );
	}

	public function test_get_and_set_category(): void
	{
		$event = new Event();
		$category = new EventCategory();
		$category->setId( 10 );
		$category->setName( 'Conferences' );

		$this->assertNull( $event->getCategory() );

		$event->setCategory( $category );

		$this->assertSame( $category, $event->getCategory() );
		$this->assertEquals( 10, $event->getCategoryId() );
	}

	public function test_set_category_null(): void
	{
		$event = new Event();

		$event->setCategory( null );

		$this->assertNull( $event->getCategory() );
	}

	public function test_get_status_defaults_to_draft(): void
	{
		$event = new Event();

		$this->assertEquals( Event::STATUS_DRAFT, $event->getStatus() );
	}

	public function test_set_status(): void
	{
		$event = new Event();

		$event->setStatus( Event::STATUS_PUBLISHED );

		$this->assertEquals( Event::STATUS_PUBLISHED, $event->getStatus() );
	}

	public function test_is_published(): void
	{
		$event = new Event();

		$this->assertFalse( $event->isPublished() );

		$event->setStatus( Event::STATUS_PUBLISHED );

		$this->assertTrue( $event->isPublished() );
	}

	public function test_is_draft(): void
	{
		$event = new Event();

		$this->assertTrue( $event->isDraft() );

		$event->setStatus( Event::STATUS_PUBLISHED );

		$this->assertFalse( $event->isDraft() );
	}

	public function test_get_and_set_featured_image(): void
	{
		$event = new Event();

		$this->assertNull( $event->getFeaturedImage() );

		$event->setFeaturedImage( '/images/conference.jpg' );

		$this->assertEquals( '/images/conference.jpg', $event->getFeaturedImage() );
	}

	public function test_get_and_set_organizer(): void
	{
		$event = new Event();

		$this->assertNull( $event->getOrganizer() );

		$event->setOrganizer( 'Tech Events Inc.' );

		$this->assertEquals( 'Tech Events Inc.', $event->getOrganizer() );
	}

	public function test_get_and_set_contact_email(): void
	{
		$event = new Event();

		$this->assertNull( $event->getContactEmail() );

		$event->setContactEmail( 'contact@techevents.com' );

		$this->assertEquals( 'contact@techevents.com', $event->getContactEmail() );
	}

	public function test_get_and_set_contact_phone(): void
	{
		$event = new Event();

		$this->assertNull( $event->getContactPhone() );

		$event->setContactPhone( '+1-555-1234' );

		$this->assertEquals( '+1-555-1234', $event->getContactPhone() );
	}

	public function test_get_and_set_created_by(): void
	{
		$event = new Event();

		$this->assertNull( $event->getCreatedBy() );

		$event->setCreatedBy( 42 );

		$this->assertEquals( 42, $event->getCreatedBy() );
	}

	public function test_get_and_set_creator(): void
	{
		$event = new Event();
		$user = new User();
		$user->setId( 99 );

		$this->assertNull( $event->getCreator() );

		$event->setCreator( $user );

		$this->assertSame( $user, $event->getCreator() );
		$this->assertEquals( 99, $event->getCreatedBy() );
	}

	public function test_set_creator_null_clears_created_by(): void
	{
		$event = new Event();
		$user = new User();
		$user->setId( 50 );

		$event->setCreator( $user );
		$this->assertEquals( 50, $event->getCreatedBy() );

		$event->setCreator( null );

		$this->assertNull( $event->getCreator() );
		$this->assertNull( $event->getCreatedBy() );
	}

	public function test_set_creator_without_id_sets_created_by_to_null(): void
	{
		$event = new Event();
		$user = new User();
		// User has no ID

		$event->setCreator( $user );

		$this->assertSame( $user, $event->getCreator() );
		$this->assertNull( $event->getCreatedBy() );
	}

	public function test_get_view_count_defaults_to_zero(): void
	{
		$event = new Event();

		$this->assertEquals( 0, $event->getViewCount() );
	}

	public function test_set_view_count(): void
	{
		$event = new Event();

		$event->setViewCount( 150 );

		$this->assertEquals( 150, $event->getViewCount() );
	}

	public function test_increment_view_count(): void
	{
		$event = new Event();

		$event->setViewCount( 10 );
		$event->incrementViewCount();

		$this->assertEquals( 11, $event->getViewCount() );

		$event->incrementViewCount();

		$this->assertEquals( 12, $event->getViewCount() );
	}

	public function test_get_and_set_updated_at(): void
	{
		$event = new Event();

		$this->assertNull( $event->getUpdatedAt() );

		$updatedAt = new DateTimeImmutable( '2025-01-01 12:00:00' );
		$event->setUpdatedAt( $updatedAt );

		$this->assertSame( $updatedAt, $event->getUpdatedAt() );
	}

	public function test_is_upcoming_returns_true_for_future_event(): void
	{
		$event = new Event();
		$futureDate = new DateTimeImmutable( '+1 week' );
		$event->setStartDate( $futureDate );

		$this->assertTrue( $event->isUpcoming() );
	}

	public function test_is_upcoming_returns_false_for_past_event(): void
	{
		$event = new Event();
		$pastDate = new DateTimeImmutable( '-1 week' );
		$event->setStartDate( $pastDate );

		$this->assertFalse( $event->isUpcoming() );
	}

	public function test_is_past_returns_true_for_past_event_with_end_date(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '-2 days' ) );
		$event->setEndDate( new DateTimeImmutable( '-1 day' ) );

		$this->assertTrue( $event->isPast() );
	}

	public function test_is_past_returns_true_for_past_event_without_end_date(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '-1 day' ) );

		$this->assertTrue( $event->isPast() );
	}

	public function test_is_past_returns_false_for_future_event(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '+1 day' ) );

		$this->assertFalse( $event->isPast() );
	}

	public function test_is_ongoing_returns_true_for_current_event(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '-1 hour' ) );
		$event->setEndDate( new DateTimeImmutable( '+1 hour' ) );

		$this->assertTrue( $event->isOngoing() );
	}

	public function test_is_ongoing_returns_false_for_future_event(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '+1 hour' ) );
		$event->setEndDate( new DateTimeImmutable( '+2 hours' ) );

		$this->assertFalse( $event->isOngoing() );
	}

	public function test_is_ongoing_returns_false_for_past_event(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '-2 hours' ) );
		$event->setEndDate( new DateTimeImmutable( '-1 hour' ) );

		$this->assertFalse( $event->isOngoing() );
	}

	public function test_is_ongoing_uses_start_date_when_no_end_date(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '-1 hour' ) );
		// No end date set

		$this->assertFalse( $event->isOngoing() );
	}

	public function test_from_array_creates_event_with_all_fields(): void
	{
		$data = [
			'id' => 123,
			'title' => 'Test Event',
			'slug' => 'test-event',
			'description' => 'Test description',
			'content_raw' => '{"blocks":[]}',
			'location' => 'Test Location',
			'start_date' => '2025-06-15 10:00:00',
			'end_date' => '2025-06-15 18:00:00',
			'all_day' => true,
			'category_id' => 5,
			'status' => Event::STATUS_PUBLISHED,
			'featured_image' => '/image.jpg',
			'organizer' => 'Test Organizer',
			'contact_email' => 'test@example.com',
			'contact_phone' => '555-1234',
			'created_by' => 10,
			'view_count' => 50,
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-02 10:00:00'
		];

		$event = Event::fromArray( $data );

		$this->assertEquals( 123, $event->getId() );
		$this->assertEquals( 'Test Event', $event->getTitle() );
		$this->assertEquals( 'test-event', $event->getSlug() );
		$this->assertEquals( 'Test description', $event->getDescription() );
		$this->assertEquals( '{"blocks":[]}', $event->getContentRaw() );
		$this->assertEquals( 'Test Location', $event->getLocation() );
		$this->assertEquals( '2025-06-15 10:00:00', $event->getStartDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2025-06-15 18:00:00', $event->getEndDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertTrue( $event->isAllDay() );
		$this->assertEquals( 5, $event->getCategoryId() );
		$this->assertEquals( Event::STATUS_PUBLISHED, $event->getStatus() );
		$this->assertEquals( '/image.jpg', $event->getFeaturedImage() );
		$this->assertEquals( 'Test Organizer', $event->getOrganizer() );
		$this->assertEquals( 'test@example.com', $event->getContactEmail() );
		$this->assertEquals( '555-1234', $event->getContactPhone() );
		$this->assertEquals( 10, $event->getCreatedBy() );
		$this->assertEquals( 50, $event->getViewCount() );
		$this->assertInstanceOf( DateTimeImmutable::class, $event->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $event->getUpdatedAt() );
	}

	public function test_from_array_handles_content_raw_as_array(): void
	{
		$data = [
			'title' => 'Test',
			'slug' => 'test',
			'content_raw' => ['blocks' => [['type' => 'paragraph']]]
		];

		$event = Event::fromArray( $data );

		$decoded = json_decode( $event->getContentRaw(), true );
		$this->assertEquals( $data['content_raw'], $decoded );
	}

	public function test_from_array_with_category_relationship(): void
	{
		$category = new EventCategory();
		$category->setId( 15 );

		$data = [
			'title' => 'Test',
			'slug' => 'test',
			'category' => $category
		];

		$event = Event::fromArray( $data );

		$this->assertSame( $category, $event->getCategory() );
		$this->assertEquals( 15, $event->getCategoryId() );
	}

	public function test_from_array_with_creator_relationship(): void
	{
		$user = new User();
		$user->setId( 25 );

		$data = [
			'title' => 'Test',
			'slug' => 'test',
			'creator' => $user
		];

		$event = Event::fromArray( $data );

		$this->assertSame( $user, $event->getCreator() );
	}

	public function test_to_array_includes_all_fields(): void
	{
		$event = new Event();
		$event->setId( 456 );
		$event->setTitle( 'Test Event' );
		$event->setSlug( 'test-event' );
		$event->setDescription( 'Description' );
		$event->setLocation( 'Location' );
		$event->setStartDate( new DateTimeImmutable( '2025-06-15 10:00:00' ) );
		$event->setEndDate( new DateTimeImmutable( '2025-06-15 18:00:00' ) );
		$event->setAllDay( true );
		$event->setCategoryId( 7 );
		$event->setStatus( Event::STATUS_PUBLISHED );
		$event->setFeaturedImage( '/test.jpg' );
		$event->setOrganizer( 'Organizer' );
		$event->setContactEmail( 'email@test.com' );
		$event->setContactPhone( '123-456' );
		$event->setCreatedBy( 30 );
		$event->setViewCount( 100 );

		$array = $event->toArray();

		$this->assertEquals( 456, $array['id'] );
		$this->assertEquals( 'Test Event', $array['title'] );
		$this->assertEquals( 'test-event', $array['slug'] );
		$this->assertEquals( 'Description', $array['description'] );
		$this->assertEquals( 'Location', $array['location'] );
		$this->assertEquals( '2025-06-15 10:00:00', $array['start_date'] );
		$this->assertEquals( '2025-06-15 18:00:00', $array['end_date'] );
		$this->assertTrue( $array['all_day'] );
		$this->assertEquals( 7, $array['category_id'] );
		$this->assertEquals( Event::STATUS_PUBLISHED, $array['status'] );
		$this->assertEquals( '/test.jpg', $array['featured_image'] );
		$this->assertEquals( 'Organizer', $array['organizer'] );
		$this->assertEquals( 'email@test.com', $array['contact_email'] );
		$this->assertEquals( '123-456', $array['contact_phone'] );
		$this->assertEquals( 30, $array['created_by'] );
		$this->assertEquals( 100, $array['view_count'] );
	}

	public function test_to_array_excludes_id_when_null(): void
	{
		$event = new Event();
		$event->setTitle( 'Test' );
		$event->setSlug( 'test' );

		$array = $event->toArray();

		$this->assertArrayNotHasKey( 'id', $array );
	}

	public function test_to_array_handles_null_optional_fields(): void
	{
		$event = new Event();
		$event->setTitle( 'Test' );
		$event->setSlug( 'test' );
		$event->setStartDate( new DateTimeImmutable() );

		$array = $event->toArray();

		$this->assertNull( $array['description'] );
		$this->assertNull( $array['location'] );
		$this->assertNull( $array['end_date'] );
		$this->assertNull( $array['category_id'] );
		$this->assertNull( $array['featured_image'] );
		$this->assertNull( $array['organizer'] );
		$this->assertNull( $array['contact_email'] );
		$this->assertNull( $array['contact_phone'] );
		$this->assertNull( $array['created_by'] );
		$this->assertNull( $array['updated_at'] );
	}
}
