<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\Updater;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class UpdaterTest extends TestCase
{
	private Updater $updater;
	private $eventRepository;
	private $categoryRepository;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( IEventRepository::class );
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$this->updater = new Updater(
			$this->eventRepository,
			$this->categoryRepository
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		int $id,
		string $title,
		string $startDate,
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
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/events/update-event-request.yaml' );
		$dto = $factory->create();

		$dto->id = $id;
		$dto->title = $title;
		$dto->start_date = $startDate;
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

	public function test_update_basic_event(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setTitle( 'Old Title' );
		$event->setSlug( 'old-slug' );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->eventRepository->expects( $this->never() )
			->method( 'slugExists' );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED
		);

		$result = $this->updater->update( $dto );

		$this->assertInstanceOf( Event::class, $result );
		$this->assertEquals( 'New Title', $event->getTitle() );
		$this->assertEquals( $newStartDate, $event->getStartDate() );
		$this->assertEquals( Event::STATUS_PUBLISHED, $event->getStatus() );
		// Slug should not change when not provided
		$this->assertEquals( 'old-slug', $event->getSlug() );
	}

	public function test_update_with_new_slug(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setSlug( 'old-slug' );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'new-slug', 1 )
			->willReturn( false );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED,
			slug: 'new-slug'
		);

		$this->updater->update( $dto );

		$this->assertEquals( 'new-slug', $event->getSlug() );
	}

	public function test_update_with_all_fields(): void
	{
		$event = new Event();
		$event->setId( 5 );

		$newStartDate = new DateTimeImmutable( '2025-08-01 09:00:00' );
		$newEndDate = new DateTimeImmutable( '2025-08-01 17:00:00' );

		$category = new EventCategory();
		$category->setId( 2 );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 5 )
			->willReturn( $event );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 2 )
			->willReturn( $category );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'updated-slug', 5 )
			->willReturn( false );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 5,
			title: 'Updated Conference',
			startDate: '2025-08-01 09:00:00',
			status: Event::STATUS_PUBLISHED,
			slug: 'updated-slug',
			description: 'Updated description',
			content: '{"blocks":[{"type":"paragraph","data":{"text":"Updated content"}}]}',
			location: 'New Location',
			endDate: '2025-08-01 17:00:00',
			allDay: true,
			categoryId: 2,
			featuredImage: '/images/updated.jpg',
			organizer: 'Updated Organizer',
			contactEmail: 'updated@example.com',
			contactPhone: '555-9999'
		);

		$this->updater->update( $dto );

		$this->assertEquals( 'Updated Conference', $event->getTitle() );
		$this->assertEquals( 'updated-slug', $event->getSlug() );
		$this->assertEquals( 'Updated description', $event->getDescription() );
		$this->assertEquals( 'New Location', $event->getLocation() );
		$this->assertEquals( $newEndDate, $event->getEndDate() );
		$this->assertTrue( $event->isAllDay() );
		$this->assertEquals( 2, $event->getCategoryId() );
		$this->assertEquals( '/images/updated.jpg', $event->getFeaturedImage() );
		$this->assertEquals( 'Updated Organizer', $event->getOrganizer() );
		$this->assertEquals( 'updated@example.com', $event->getContactEmail() );
		$this->assertEquals( '555-9999', $event->getContactPhone() );
	}

	public function test_update_throws_exception_when_slug_exists_for_other_event(): void
	{
		$event = new Event();
		$event->setId( 1 );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'duplicate-slug', 1 )
			->willReturn( true );

		$this->eventRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'An event with this slug already exists' );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED,
			slug: 'duplicate-slug'
		);

		$this->updater->update( $dto );
	}

	public function test_update_throws_exception_when_category_not_found(): void
	{
		$event = new Event();
		$event->setId( 1 );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->eventRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event category not found' );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED,
			categoryId: 999
		);

		$this->updater->update( $dto );
	}

	public function test_update_allows_same_slug_for_same_event(): void
	{
		$event = new Event();
		$event->setId( 5 );
		$event->setSlug( 'my-event' );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 5 )
			->willReturn( $event );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-event', 5 )  // Should exclude event ID 5
			->willReturn( false );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 5,
			title: 'Updated Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED,
			slug: 'my-event'
		);

		$this->updater->update( $dto );

		$this->assertEquals( 'my-event', $event->getSlug() );
	}

	public function test_update_removes_category_when_null(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setCategoryId( 5 );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->categoryRepository->expects( $this->never() )
			->method( 'findById' );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED,
			categoryId: null
		);

		$this->updater->update( $dto );

		$this->assertNull( $event->getCategoryId() );
	}

	public function test_update_changes_status_from_draft_to_published(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setStatus( Event::STATUS_DRAFT );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $event );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$dto = $this->createDto(
			id: 1,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED
		);

		$this->updater->update( $dto );

		$this->assertEquals( Event::STATUS_PUBLISHED, $event->getStatus() );
	}

	public function test_constructor_sets_properties_correctly(): void
	{
		$eventRepository = $this->createMock( IEventRepository::class );
		$categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$updater = new Updater( $eventRepository, $categoryRepository );

		$this->assertInstanceOf( Updater::class, $updater );
	}

	public function test_update_throws_exception_when_event_not_found(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event with ID 999 not found' );

		$dto = $this->createDto(
			id: 999,
			title: 'New Title',
			startDate: '2025-07-01 14:00:00',
			status: Event::STATUS_PUBLISHED
		);

		$this->updater->update( $dto );
	}
}
