<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Services\Widget\FeaturedEventWidget;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use PHPUnit\Framework\TestCase;

class FeaturedEventWidgetTest extends TestCase
{
	private $eventRepository;
	private $widget;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( DatabaseEventRepository::class );
		$this->widget = new FeaturedEventWidget( $this->eventRepository );
	}

	public function testGetNameReturnsFeaturedEvent(): void
	{
		$this->assertEquals( 'featured-event', $this->widget->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$description = $this->widget->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testGetAttributesReturnsArray(): void
	{
		$this->assertIsArray( $this->widget->getAttributes() );
	}

	public function testRenderQueriesPublishedFeaturedEvent(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getNextFeatured' )
			->with( 'published' )
			->willReturn( null );

		$this->widget->render( [] );
	}

	public function testRenderWithNoEventReturnsComment(): void
	{
		$this->eventRepository->method( 'getNextFeatured' )->willReturn( null );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<!-- Featured event widget: no featured event available -->', $result );
	}

	public function testRenderWithEventGeneratesCard(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Big Gala' );
		$event->method( 'getSlug' )->willReturn( 'big-gala' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2030-01-15 18:30:00' ) );
		$event->method( 'isAllDay' )->willReturn( false );
		$event->method( 'getLocation' )->willReturn( 'Grand Hall' );
		$event->method( 'getDescription' )->willReturn( 'An evening to remember' );
		$event->method( 'getFeaturedImage' )->willReturn( null );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( 'featured-event-widget', $result );
		$this->assertStringContainsString( 'Big Gala', $result );
		$this->assertStringContainsString( '/calendar/event/big-gala', $result );
		$this->assertStringContainsString( 'January 15, 2030 at 6:30 PM', $result );
		$this->assertStringContainsString( 'Grand Hall', $result );
		$this->assertStringContainsString( 'An evening to remember', $result );
	}

	public function testRenderWithAllDayEventDoesNotShowTime(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'All Day Festival' );
		$event->method( 'getSlug' )->willReturn( 'all-day-festival' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2030-01-15 00:00:00' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( null );
		$event->method( 'getDescription' )->willReturn( null );
		$event->method( 'getFeaturedImage' )->willReturn( null );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( 'January 15, 2030', $result );
		$this->assertStringNotContainsString( ' at ', $result );
		$this->assertStringNotContainsString( 'AM', $result );
		$this->assertStringNotContainsString( 'PM', $result );
	}

	public function testRenderIncludesFeaturedImageWhenPresent(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Image Event' );
		$event->method( 'getSlug' )->willReturn( 'image-event' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2030-01-15' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( null );
		$event->method( 'getDescription' )->willReturn( null );
		$event->method( 'getFeaturedImage' )->willReturn( 'https://example.com/img.jpg' );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<img src="https://example.com/img.jpg"', $result );
	}

	public function testRenderImageModeReturnsOnlyLinkedImage(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Sponsored Gala' );
		$event->method( 'getSlug' )->willReturn( 'sponsored-gala' );
		$event->method( 'getFeaturedImage' )->willReturn( 'https://example.com/cover.jpg' );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [ 'display' => 'image' ] );

		$this->assertStringContainsString( '<img class="featured-event-image-only" src="https://example.com/cover.jpg"', $result );
		$this->assertStringContainsString( 'href="/calendar/event/sponsored-gala"', $result );

		// Image mode omits the card chrome.
		$this->assertStringNotContainsString( 'featured-event-widget', $result );
		$this->assertStringNotContainsString( 'featured-event-badge', $result );
		$this->assertStringNotContainsString( 'View details', $result );
	}

	public function testRenderImageModeWithLinkFalseOmitsAnchor(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'Sponsored Gala' );
		$event->method( 'getSlug' )->willReturn( 'sponsored-gala' );
		$event->method( 'getFeaturedImage' )->willReturn( 'https://example.com/cover.jpg' );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [ 'display' => 'image', 'link' => false ] );

		$this->assertStringContainsString( '<img class="featured-event-image-only"', $result );
		$this->assertStringNotContainsString( '<a ', $result );
	}

	public function testRenderImageModeWithNoImageReturnsComment(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( 'No Image Event' );
		$event->method( 'getSlug' )->willReturn( 'no-image-event' );
		$event->method( 'getFeaturedImage' )->willReturn( null );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [ 'display' => 'image' ] );

		$this->assertStringContainsString( '<!-- Featured event widget: featured event has no image -->', $result );
		$this->assertStringNotContainsString( '<img', $result );
	}

	public function testRenderImageModeEscapesTitleInAlt(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( '<script>alert("XSS")</script>' );
		$event->method( 'getSlug' )->willReturn( 'safe-slug' );
		$event->method( 'getFeaturedImage' )->willReturn( 'https://example.com/cover.jpg' );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [ 'display' => 'image' ] );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	public function testRenderEscapesHtmlInEventData(): void
	{
		$event = $this->createMock( \Neuron\Cms\Models\Event::class );
		$event->method( 'getTitle' )->willReturn( '<script>alert("XSS")</script>' );
		$event->method( 'getSlug' )->willReturn( 'safe-slug' );
		$event->method( 'getStartDate' )->willReturn( new \DateTimeImmutable( '2030-01-15' ) );
		$event->method( 'isAllDay' )->willReturn( true );
		$event->method( 'getLocation' )->willReturn( '<img src=x onerror=alert(1)>' );
		$event->method( 'getDescription' )->willReturn( null );
		$event->method( 'getFeaturedImage' )->willReturn( null );

		$this->eventRepository->method( 'getNextFeatured' )->willReturn( $event );

		$result = $this->widget->render( [] );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '<img src=x', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}
}
