<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Services\Widget\EventRegistrationWidget;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventRegistrationWidgetTest extends TestCase
{
	private function session( bool $loggedIn = false ): SessionManager
	{
		$session = $this->createMock( SessionManager::class );
		$session->method( 'isStarted' )->willReturn( true );
		$session->method( 'has' )->willReturnCallback( fn( $key ) => $loggedIn && $key === 'user_id' );
		$session->method( 'getFlash' )->willReturn( null );

		return $session;
	}

	private function event( int $id, string $title, bool $private = false, bool $registration = true, ?int $capacity = null ): Event
	{
		$event = new Event();
		$event->setId( $id );
		$event->setTitle( $title );
		$event->setSlug( 'event-' . $id );
		$event->setStartDate( new DateTimeImmutable( '2030-06-01 09:00:00' ) );
		$event->setStatus( Event::STATUS_PUBLISHED );
		$event->setRegistrationEnabled( $registration );
		$event->setRegistrationVisibility( $private ? Event::VISIBILITY_PRIVATE : Event::VISIBILITY_PUBLIC );
		$event->setCapacity( $capacity );

		return $event;
	}

	private function widget(
		?IEventRepository $eventRepo = null,
		?IEventCategoryRepository $categoryRepo = null,
		?IEventRegistrationRepository $registrationRepo = null,
		bool $loggedIn = false
	): EventRegistrationWidget
	{
		return new EventRegistrationWidget(
			$eventRepo ?? $this->createMock( IEventRepository::class ),
			$categoryRepo ?? $this->createMock( IEventCategoryRepository::class ),
			$registrationRepo,
			$this->session( $loggedIn )
		);
	}

	public function testReturnsCommentWhenNoAttributes(): void
	{
		$this->assertStringContainsString( '<!--', $this->widget()->render( [] ) );
	}

	public function testSingleEventRendersForm(): void
	{
		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'findBySlug' )->willReturn( $this->event( 5, 'Open House' ) );

		$html = $this->widget( $eventRepo )->render( [ 'event' => 'event-5' ] );

		$this->assertStringContainsString( 'data-event-registration-form', $html );
		$this->assertStringContainsString( 'name="event_id" value="5"', $html );
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
	}

	public function testPrivateEventShowsLoginPromptForGuest(): void
	{
		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'findBySlug' )->willReturn( $this->event( 5, 'Members Night', true ) );

		$html = $this->widget( $eventRepo, null, null, false )->render( [ 'event' => 'event-5' ] );

		$this->assertStringContainsString( 'log in', $html );
		$this->assertStringNotContainsString( 'data-event-registration-form', $html );
	}

	public function testPrivateEventRendersFormForMember(): void
	{
		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'findBySlug' )->willReturn( $this->event( 5, 'Members Night', true ) );

		$html = $this->widget( $eventRepo, null, null, true )->render( [ 'event' => 'event-5' ] );

		$this->assertStringContainsString( 'data-event-registration-form', $html );
	}

	public function testSingleEventShowsFullMessageWhenAtCapacity(): void
	{
		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'findBySlug' )->willReturn( $this->event( 5, 'Workshop', false, true, 10 ) );

		$registrationRepo = $this->createMock( IEventRegistrationRepository::class );
		$registrationRepo->method( 'countByEvent' )->willReturn( 10 );

		$html = $this->widget( $eventRepo, null, $registrationRepo )->render( [ 'event' => 'event-5' ] );

		$this->assertStringContainsString( 'full', $html );
		$this->assertStringNotContainsString( 'data-event-registration-form', $html );
	}

	public function testSingleEventRendersFormWhenBelowCapacity(): void
	{
		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'findBySlug' )->willReturn( $this->event( 5, 'Workshop', false, true, 10 ) );

		$registrationRepo = $this->createMock( IEventRegistrationRepository::class );
		$registrationRepo->method( 'countByEvent' )->willReturn( 9 );

		$html = $this->widget( $eventRepo, null, $registrationRepo )->render( [ 'event' => 'event-5' ] );

		$this->assertStringContainsString( 'data-event-registration-form', $html );
	}

	public function testCategoryModeRendersSelectOfUpcomingEvents(): void
	{
		$category = EventCategory::fromArray( [ 'id' => 9, 'name' => 'Workshops', 'slug' => 'workshops', 'color' => '#3b82f6' ] );

		$categoryRepo = $this->createMock( IEventCategoryRepository::class );
		$categoryRepo->method( 'findBySlug' )->willReturn( $category );

		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'getUpcomingByCategory' )->willReturn( [
			$this->event( 1, 'Intro Workshop' ),
			$this->event( 2, 'Advanced Workshop' )
		] );

		$html = $this->widget( $eventRepo, $categoryRepo )->render( [ 'category' => 'workshops' ] );

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'name="event_id"', $html );
		$this->assertStringContainsString( 'value="1"', $html );
		$this->assertStringContainsString( 'value="2"', $html );
	}

	public function testCategoryModeHidesPrivateEventsFromGuests(): void
	{
		$category = EventCategory::fromArray( [ 'id' => 9, 'name' => 'Workshops', 'slug' => 'workshops', 'color' => '#3b82f6' ] );

		$categoryRepo = $this->createMock( IEventCategoryRepository::class );
		$categoryRepo->method( 'findBySlug' )->willReturn( $category );

		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'getUpcomingByCategory' )->willReturn( [
			$this->event( 1, 'Public Workshop', false ),
			$this->event( 2, 'Members Workshop', true )
		] );

		$html = $this->widget( $eventRepo, $categoryRepo, null, false )->render( [ 'category' => 'workshops' ] );

		$this->assertStringContainsString( 'value="1"', $html );
		$this->assertStringNotContainsString( 'value="2"', $html );
	}

	public function testCategoryModeHidesFullEvents(): void
	{
		$category = EventCategory::fromArray( [ 'id' => 9, 'name' => 'Workshops', 'slug' => 'workshops', 'color' => '#3b82f6' ] );

		$categoryRepo = $this->createMock( IEventCategoryRepository::class );
		$categoryRepo->method( 'findBySlug' )->willReturn( $category );

		$eventRepo = $this->createMock( IEventRepository::class );
		$eventRepo->method( 'getUpcomingByCategory' )->willReturn( [
			$this->event( 1, 'Open Workshop', false, true, 10 ),
			$this->event( 2, 'Full Workshop', false, true, 5 )
		] );

		$registrationRepo = $this->createMock( IEventRegistrationRepository::class );
		$registrationRepo->method( 'countByEvent' )->willReturnCallback(
			fn( int $eventId ) => $eventId === 2 ? 5 : 0
		);

		$html = $this->widget( $eventRepo, $categoryRepo, $registrationRepo )->render( [ 'category' => 'workshops' ] );

		$this->assertStringContainsString( 'value="1"', $html );
		$this->assertStringNotContainsString( 'value="2"', $html );
	}

	public function testUnknownCategoryReturnsComment(): void
	{
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );
		$categoryRepo->method( 'findBySlug' )->willReturn( null );

		$this->assertStringContainsString( '<!--', $this->widget( null, $categoryRepo )->render( [ 'category' => 'nope' ] ) );
	}
}
