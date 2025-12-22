<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\Updater;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
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

	public function test_update_basic_event(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setTitle( 'Old Title' );
		$event->setSlug( 'old-slug' );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->never() )
			->method( 'slugExists' );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$result = $this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED
		);

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
			->method( 'slugExists' )
			->with( 'new-slug', 1 )
			->willReturn( false );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			'new-slug'
		);

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

		$this->updater->update(
			$event,
			'Updated Conference',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			'updated-slug',
			'Updated description',
			'{"blocks":[{"type":"paragraph","data":{"text":"Updated content"}}]}',
			'New Location',
			$newEndDate,
			true,
			2,
			'/images/updated.jpg',
			'Updated Organizer',
			'updated@example.com',
			'555-9999'
		);

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
			->method( 'slugExists' )
			->with( 'duplicate-slug', 1 )
			->willReturn( true );

		$this->eventRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'An event with this slug already exists' );

		$this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			'duplicate-slug'
		);
	}

	public function test_update_throws_exception_when_category_not_found(): void
	{
		$event = new Event();
		$event->setId( 1 );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->eventRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Event category not found' );

		$this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			null,
			null,
			'{"blocks":[]}',
			null,
			null,
			false,
			999  // Non-existent category
		);
	}

	public function test_update_allows_same_slug_for_same_event(): void
	{
		$event = new Event();
		$event->setId( 5 );
		$event->setSlug( 'my-event' );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-event', 5 )  // Should exclude event ID 5
			->willReturn( false );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$this->updater->update(
			$event,
			'Updated Title',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			'my-event'  // Same slug
		);

		$this->assertEquals( 'my-event', $event->getSlug() );
	}

	public function test_update_removes_category_when_null(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setCategoryId( 5 );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->categoryRepository->expects( $this->never() )
			->method( 'findById' );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED,
			null,
			null,
			'{"blocks":[]}',
			null,
			null,
			false,
			null  // Remove category
		);

		$this->assertNull( $event->getCategoryId() );
	}

	public function test_update_changes_status_from_draft_to_published(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setStatus( Event::STATUS_DRAFT );

		$newStartDate = new DateTimeImmutable( '2025-07-01 14:00:00' );

		$this->eventRepository->expects( $this->once() )
			->method( 'update' )
			->with( $event )
			->willReturn( $event );

		$this->updater->update(
			$event,
			'New Title',
			$newStartDate,
			Event::STATUS_PUBLISHED
		);

		$this->assertEquals( Event::STATUS_PUBLISHED, $event->getStatus() );
	}
}
