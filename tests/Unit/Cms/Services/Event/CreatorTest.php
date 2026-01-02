<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\Creator;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class CreatorTest extends TestCase
{
	private Creator $creator;
	private $eventRepository;
	private $categoryRepository;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( IEventRepository::class );
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$this->creator = new Creator(
			$this->eventRepository,
			$this->categoryRepository
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		string $title,
		string $startDate,
		int $createdBy,
		string $status,
		?string $slug = null,
		?string $description = null,
		?string $content = null,
		?string $location = null,
		?string $endDate = null,
		?bool $allDay = null,
		?int $categoryId = null,
		?string $featuredImage = null,
		?string $organizer = null,
		?string $contactEmail = null,
		?string $contactPhone = null
	): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/events/create-event-request.yaml" );
		$dto = $factory->create();

		$dto->title = $title;
		$dto->start_date = $startDate;
		$dto->created_by = $createdBy;
		$dto->status = $status;

		if( $slug !== null )
		{
			$dto->slug = $slug;
		}
		if( $description !== null )
		{
			$dto->description = $description;
		}
		if( $content !== null )
		{
			$dto->content = $content;
		}
		if( $location !== null )
		{
			$dto->location = $location;
		}
		if( $endDate !== null )
		{
			$dto->end_date = $endDate;
		}
		if( $allDay !== null )
		{
			$dto->all_day = $allDay;
		}
		if( $categoryId !== null )
		{
			$dto->category_id = $categoryId;
		}
		if( $featuredImage !== null )
		{
			$dto->featured_image = $featuredImage;
		}
		if( $organizer !== null )
		{
			$dto->organizer = $organizer;
		}
		if( $contactEmail !== null )
		{
			$dto->contact_email = $contactEmail;
		}
		if( $contactPhone !== null )
		{
			$dto->contact_phone = $contactPhone;
		}

		return $dto;
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

		$dto = $this->createDto(
			title: 'Test Event',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT
		);

		$result = $this->creator->create( $dto );

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

		$dto = $this->createDto(
			title: 'Test Event',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT,
			slug: 'custom-slug'
		);

		$this->creator->create( $dto );

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

		$dto = $this->createDto(
			title: 'Tech Conference',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_PUBLISHED,
			slug: 'tech-conf',
			description: 'A great tech event',
			content: '{"blocks":[{"type":"paragraph","data":{"text":"Hello"}}]}',
			location: 'Convention Center',
			endDate: '2025-06-15 17:00:00',
			allDay: false,
			categoryId: 3,
			featuredImage: '/images/tech.jpg',
			organizer: 'Tech Org',
			contactEmail: 'info@tech.com',
			contactPhone: '555-1234'
		);

		$this->creator->create( $dto );

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

		$dto = $this->createDto(
			title: 'My Awesome Event!!!',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT
		);

		$this->creator->create( $dto );

		$this->assertEquals( 'my-awesome-event', $capturedEvent->getSlug() );
	}

	public function test_create_handles_non_ascii_title(): void
	{
		$startDate = new DateTimeImmutable( '2025-06-15 10:00:00' );

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

		$dto = $this->createDto(
			title: '日本語イベント',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT
		);

		$this->creator->create( $dto );

		// Non-ASCII title should generate fallback slug with pattern event-{uniqueid}
		$this->assertMatchesRegularExpression( '/^event-[a-z0-9]+$/', $capturedEvent->getSlug() );
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

		$dto = $this->createDto(
			title: 'Test Event',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT,
			categoryId: 999
		);

		$this->creator->create( $dto );
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

		$dto = $this->createDto(
			title: 'Test Event',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT,
			slug: 'duplicate-slug'
		);

		$this->creator->create( $dto );
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

		$dto = $this->createDto(
			title: 'All Day Event',
			startDate: '2025-06-15 00:00:00',
			createdBy: 5,
			status: Event::STATUS_PUBLISHED,
			allDay: true
		);

		$this->creator->create( $dto );

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

		$dto = $this->createDto(
			title: 'No Category Event',
			startDate: '2025-06-15 10:00:00',
			createdBy: 5,
			status: Event::STATUS_DRAFT
		);

		$this->creator->create( $dto );

		$this->assertNull( $capturedEvent->getCategoryId() );
	}
}
