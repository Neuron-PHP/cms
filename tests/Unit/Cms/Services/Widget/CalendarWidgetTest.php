<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Services\Widget\CalendarWidget;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use PHPUnit\Framework\TestCase;

class CalendarWidgetTest extends TestCase
{
	private $eventRepository;
	private $categoryRepository;
	private $widget;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( DatabaseEventRepository::class );
		$this->categoryRepository = $this->createMock( DatabaseEventCategoryRepository::class );

		$this->widget = new CalendarWidget(
			$this->eventRepository,
			$this->categoryRepository
		);
	}

	public function test_upcoming_defaults_to_true_when_not_specified(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( [] );
	}

	public function test_upcoming_true_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => true] );
	}

	public function test_upcoming_false_string_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => 'false'] );
	}

	public function test_upcoming_false_boolean_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => false] );
	}

	public function test_upcoming_zero_string_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => '0'] );
	}

	public function test_upcoming_one_string_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => '1'] );
	}

	public function test_upcoming_true_string_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => 'true'] );
	}

	public function test_limit_is_respected(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 10, 'published' )
			->willReturn( [] );

		$this->widget->render( ['limit' => 10] );
	}

	public function test_limit_defaults_to_5(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( [] );
	}

	public function testGetNameReturnsCalendar(): void
	{
		$this->assertEquals( 'calendar', $this->widget->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$description = $this->widget->getDescription();
		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
		$this->assertEquals( 'Display a list of calendar events', $description );
	}

	public function testGetAttributesReturnsExpectedStructure(): void
	{
		$attributes = $this->widget->getAttributes();

		$this->assertIsArray( $attributes );
		$this->assertArrayHasKey( 'category', $attributes );
		$this->assertArrayHasKey( 'limit', $attributes );
		$this->assertArrayHasKey( 'upcoming', $attributes );

		$this->assertIsString( $attributes['category'] );
		$this->assertIsString( $attributes['limit'] );
		$this->assertIsString( $attributes['upcoming'] );
	}

	public function testRenderWithNoEventsReturnsNoEventsMessage(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [] );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( 'No events found', $result );
		$this->assertStringContainsString( 'calendar-widget', $result );
	}

	public function testRenderWithCategoryNotFoundReturnsComment(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'findBySlug' )
			->with( 'nonexistent' )
			->willReturn( null );

		$result = $this->widget->render( [ 'category' => 'nonexistent' ] );

		$this->assertStringContainsString( '<!-- Calendar widget: category \'nonexistent\' not found -->', $result );
	}

	public function testRenderWithValidCategoryFiltersEvents(): void
	{
		$category = $this->createMock( \Neuron\Cms\Models\EventCategory::class );
		$category->method( 'getId' )->willReturn( 5 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findBySlug' )
			->with( 'tech-events' )
			->willReturn( $category );

		$this->eventRepository->expects( $this->once() )
			->method( 'getByCategory' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( [ 'category' => 'tech-events' ] );
	}

	public function testRenderWithCategoryRespectsLimit(): void
	{
		$category = $this->createMock( \Neuron\Cms\Models\EventCategory::class );
		$category->method( 'getId' )->willReturn( 5 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findBySlug' )
			->with( 'tech-events' )
			->willReturn( $category );

		// Create more events than the limit
		$events = [];
		for( $i = 1; $i <= 10; $i++ )
		{
			$event = $this->createMock( \Neuron\Cms\Models\Event::class );
			$event->method( 'getTitle' )->willReturn( "Event $i" );
			$event->method( 'getSlug' )->willReturn( "event-$i" );
			$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-01' ) );
			$event->method( 'isAllDay' )->willReturn( true );
			$event->method( 'getLocation' )->willReturn( null );
			$events[] = $event;
		}

		$this->eventRepository->expects( $this->once() )
			->method( 'getByCategory' )
			->with( 5, 'published' )
			->willReturn( $events );

		$result = $this->widget->render( [ 'category' => 'tech-events', 'limit' => 3 ] );

		// Should only show 3 events
		$this->assertStringContainsString( 'Event 1', $result );
		$this->assertStringContainsString( 'Event 2', $result );
		$this->assertStringContainsString( 'Event 3', $result );
		$this->assertStringNotContainsString( 'Event 4', $result );
	}

	public function testRenderWithEventsGeneratesHtmlStructure(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Test Event' );
		$event->method( 'getSlug' )->willReturn( 'test-event' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-15 14:30:00' ) );
		$event->method( 'isAllDay' )->willReturn( false );
		$event->method( 'getLocation' )->willReturn( 'Conference Room A' );

		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [ $event ] );

		$result = $this->widget->render( [] );

		// Check HTML structure
		$this->assertStringContainsString( '<div class="calendar-widget">', $result );
		$this->assertStringContainsString( '<ul class="calendar-widget-list">', $result );
		$this->assertStringContainsString( '<li class="calendar-widget-item">', $result );
		$this->assertStringContainsString( '<a href="/calendar/event/test-event" class="event-link">', $result );
		$this->assertStringContainsString( '<span class="event-title">Test Event</span>', $result );
		$this->assertStringContainsString( '<span class="event-date">January 15, 2024 at 2:30 PM</span>', $result );
		$this->assertStringContainsString( '<span class="event-location">Conference Room A</span>', $result );
		$this->assertStringContainsString( '</ul>', $result );
		$this->assertStringContainsString( '</div>', $result );
	}

	public function testRenderWithAllDayEventDoesNotShowTime(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'All Day Event' );
		$event->method( 'getSlug' )->willReturn( 'all-day' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-15 00:00:00' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( null );

		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [ $event ] );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( 'January 15, 2024', $result );
		$this->assertStringNotContainsString( ' at ', $result );
		$this->assertStringNotContainsString( 'AM', $result );
		$this->assertStringNotContainsString( 'PM', $result );
	}

	public function testRenderWithEventWithoutLocationDoesNotShowLocationSpan(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Online Event' );
		$event->method( 'getSlug' )->willReturn( 'online-event' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-15' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( null );

		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [ $event ] );

		$result = $this->widget->render( [] );

		$this->assertStringNotContainsString( '<span class="event-location">', $result );
	}

	public function testRenderEscapesHtmlInEventData(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( '<script>alert("XSS")</script>' );
		$event->method( 'getSlug' )->willReturn( 'safe-slug' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-15' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( '<img src=x onerror=alert(1)>' );

		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [ $event ] );

		$result = $this->widget->render( [] );

		// HTML should be escaped
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '<img src=x', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringContainsString( '&lt;img', $result );
	}

	public function testRenderWithMultipleEventsShowsAllEvents(): void
	{
		$event1 = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event1->method( 'getTitle' )->willReturn( 'Event One' );
		$event1->method( 'getSlug' )->willReturn( 'event-one' );
		$event1->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-15' ) );
		$event1->method( 'isAllDay' )->willReturn( true );
		$event1->method( 'getLocation' )->willReturn( 'Location 1' );

		$event2 = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event2->method( 'getTitle' )->willReturn( 'Event Two' );
		$event2->method( 'getSlug' )->willReturn( 'event-two' );
		$event2->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2024-01-20' ) );
		$event2->method( 'isAllDay' )->willReturn( false );
		$event2->method( 'getLocation' )->willReturn( 'Location 2' );

		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->willReturn( [ $event1, $event2 ] );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( 'Event One', $result );
		$this->assertStringContainsString( 'event-one', $result );
		$this->assertStringContainsString( 'Location 1', $result );

		$this->assertStringContainsString( 'Event Two', $result );
		$this->assertStringContainsString( 'event-two', $result );
		$this->assertStringContainsString( 'Location 2', $result );

		// Should have 2 list items
		$this->assertEquals( 2, substr_count( $result, '<li class="calendar-widget-item">' ) );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$eventRepo = $this->createMock( DatabaseEventRepository::class );
		$categoryRepo = $this->createMock( DatabaseEventCategoryRepository::class );

		$widget = new CalendarWidget( $eventRepo, $categoryRepo );

		$this->assertInstanceOf( CalendarWidget::class, $widget );
	}
}
