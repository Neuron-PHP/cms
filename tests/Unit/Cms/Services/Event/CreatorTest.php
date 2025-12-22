<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\Creator;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Core\System\IRandom;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class CreatorTest extends TestCase
{
	private Creator $creator;
	private $eventRepository;
	private $categoryRepository;
	private $random;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( IEventRepository::class );
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );
		$this->random = $this->createMock( IRandom::class );

		$this->creator = new Creator(
			$this->eventRepository,
			$this->categoryRepository,
			$this->random
		);
	}

	public function test_create_basic_event(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'test-event' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$result = $this->creator->create(
			'Test Event',
			$startDate,
			5,
			Event::STATUS_DRAFT
		);

		$this->assertInstanceOf( Event::class, $result );
		$this->assertEquals( 'Test Event', $capturedEvent->getTitle() );
		$this->assertEquals( 'test-event', $capturedEvent->getSlug() );
		$this->assertEquals( $startDate, $capturedEvent->getStartDate() );
		$this->assertEquals( 5, $capturedEvent->getCreatedBy() );
		$this->assertEquals( Event::STATUS_DRAFT, $capturedEvent->getStatus() );
	}

	public function test_create_with_custom_slug(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'custom-slug' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'Test Event',
			$startDate,
			5,
			Event::STATUS_DRAFT,
			'custom-slug'
		);

		$this->assertEquals( 'custom-slug', $capturedEvent->getSlug() );
	}

	public function test_create_with_all_optional_fields(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );
		$endDate = new DateTimeImmutable( '2025-06-15 17:00:00' );

		$category = new EventCategory();
		$category->setId( 3 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 3 )
			->willReturn( $category );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'Tech Conference',
			$startDate,
			5,
			Event::STATUS_PUBLISHED,
			'tech-conf',
			'A great tech event',
			'{"blocks":[{"type":"paragraph","data":{"text":"Hello"}}]}',
			'Convention Center',
			$endDate,
			false,
			3,
			'/images/tech.jpg',
			'Tech Org',
			'info@tech.com',
			'555-1234'
		);

		$this->assertEquals( 'tech-conf', $capturedEvent->getSlug() );
		$this->assertEquals( 'A great tech event', $capturedEvent->getDescription() );
		$this->assertEquals( 'Convention Center', $capturedEvent->getLocation() );
		$this->assertEquals( $endDate, $capturedEvent->getEndDate() );
		$this->assertFalse( $capturedEvent->isAllDay() );
		$this->assertEquals( 3, $capturedEvent->getCategoryId() );
		$this->assertEquals( '/images/tech.jpg', $capturedEvent->getFeaturedImage() );
		$this->assertEquals( 'Tech Org', $capturedEvent->getOrganizer() );
		$this->assertEquals( 'info@tech.com', $capturedEvent->getContactEmail() );
		$this->assertEquals( '555-1234', $capturedEvent->getContactPhone() );
	}

	public function test_create_generates_slug_from_title(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-awesome-event' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'My Awesome Event!!!',
			$startDate,
			5,
			Event::STATUS_DRAFT
		);

		$this->assertEquals( 'my-awesome-event', $capturedEvent->getSlug() );
	}

	public function test_create_handles_non_ascii_title(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->random->expects( $this->once() )
			->method( 'uniqueId' )
			->willReturn( 'abc123' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'event-abc123' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'日本語イベント',
			$startDate,
			5,
			Event::STATUS_DRAFT
		);

		$this->assertEquals( 'event-abc123', $capturedEvent->getSlug() );
	}

	public function test_create_throws_exception_when_category_not_found(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event category not found' );

		$this->creator->create(
			'Test Event',
			$startDate,
			5,
			Event::STATUS_DRAFT,
			null,
			null,
			'{"blocks":[]}',
			null,
			null,
			false,
			999  // Non-existent category
		);
	}

	public function test_create_throws_exception_when_slug_exists(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'duplicate-slug' )
			->willReturn( true );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'An event with this slug already exists' );

		$this->creator->create(
			'Test Event',
			$startDate,
			5,
			Event::STATUS_DRAFT,
			'duplicate-slug'
		);
	}

	public function test_create_with_all_day_event(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 00:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'All Day Event',
			$startDate,
			5,
			Event::STATUS_PUBLISHED,
			null,
			null,
			'{"blocks":[]}',
			null,
			null,
			true  // all day
		);

		$this->assertTrue( $capturedEvent->isAllDay() );
	}

	public function test_create_without_category(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

		$this->categoryRepository->expects( $this->never() )
			->method( 'findById' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$capturedEvent = null;
		$this->eventRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Event $event ) use ( &$capturedEvent ) {
				$capturedEvent = $event;
				return true;
			}))
			->willReturnCallback( function( Event $event ) {
				$event->setId( 1 );
				return $event;
			});

		$this->creator->create(
			'No Category Event',
			$startDate,
			5,
			Event::STATUS_DRAFT,
			null,
			null,
			'{"blocks":[]}',
			null,
			null,
			false,
			null  // No category
		);

		$this->assertNull( $capturedEvent->getCategoryId() );
	}
}
