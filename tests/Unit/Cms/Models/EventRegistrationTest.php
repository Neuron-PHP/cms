<?php

namespace Tests\Unit\Cms\Models;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventRegistration;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventRegistrationTest extends TestCase
{
	private function event(): Event
	{
		$event = new Event();
		$event->setTitle( 'Teen Court Tour' );
		$event->setSlug( 'teen-court-tour' );
		$event->setStartDate( new DateTimeImmutable( '2026-08-12 09:00:00' ) );

		return $event;
	}

	public function testGetDisplayDateUsesOccurrenceWhenSet(): void
	{
		$registration = new EventRegistration();
		$registration->setOccurrenceDate( new DateTimeImmutable( '2026-09-23 09:00:00' ) );

		$this->assertSame(
			'2026-09-23 09:00:00',
			$registration->getDisplayDate( $this->event() )->format( 'Y-m-d H:i:s' )
		);
	}

	public function testGetDisplayDateFallsBackToEventStart(): void
	{
		$registration = new EventRegistration();

		$this->assertSame(
			'2026-08-12 09:00:00',
			$registration->getDisplayDate( $this->event() )->format( 'Y-m-d H:i:s' )
		);
	}
}
