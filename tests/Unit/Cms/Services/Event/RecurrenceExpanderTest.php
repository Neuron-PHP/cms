<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Services\Event\RecurrenceExpander;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class RecurrenceExpanderTest extends TestCase
{
	private function master( string $rrule, string $start = '2026-01-05 09:00:00', ?string $end = '2026-01-05 10:00:00' ): Event
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setTitle( 'Standup' );
		$event->setSlug( 'standup' );
		$event->setStartDate( new DateTimeImmutable( $start ) );
		$event->setEndDate( $end ? new DateTimeImmutable( $end ) : null );
		$event->setStatus( 'published' );
		$event->setRrule( $rrule );

		return $event;
	}

	public function testNonRecurringReturnsSelfWhenInRange(): void
	{
		$event = new Event();
		$event->setStartDate( new DateTimeImmutable( '2026-01-10 09:00:00' ) );
		$event->setEndDate( new DateTimeImmutable( '2026-01-10 10:00:00' ) );

		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$event,
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-01-31 23:59:59' )
		);

		$this->assertCount( 1, $result );
		$this->assertSame( $event, $result[0] );
	}

	public function testExpandsWeeklyOccurrences(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=WEEKLY' ),
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-01-31 23:59:59' )
		);

		// Jan 5, 12, 19, 26
		$this->assertCount( 4, $result );
		$this->assertSame( '2026-01-05 09:00:00', $result[0]->getStartDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-01-26 09:00:00', $result[3]->getStartDate()->format( 'Y-m-d H:i:s' ) );
	}

	public function testOccurrencePreservesDurationAndIsTagged(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=WEEKLY' ),
			new DateTimeImmutable( '2026-01-05 00:00:00' ),
			new DateTimeImmutable( '2026-01-12 23:59:59' )
		);

		$first = $result[0];
		$this->assertTrue( $first->isOccurrence() );
		$this->assertSame( '2026-01-05 09:00:00', $first->getOccurrenceDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-01-05 10:00:00', $first->getEndDate()->format( 'Y-m-d H:i:s' ) );
	}

	public function testExcludedDatesAreSkipped(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=WEEKLY' ),
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-01-31 23:59:59' ),
			[ '2026-01-12 09:00:00' ]
		);

		$starts = array_map( fn( $e ) => $e->getStartDate()->format( 'Y-m-d H:i:s' ), $result );
		$this->assertNotContains( '2026-01-12 09:00:00', $starts );
		$this->assertCount( 3, $result );
	}

	public function testOverriddenDatesAreSkipped(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=WEEKLY' ),
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-01-31 23:59:59' ),
			[],
			[ '2026-01-19 09:00:00' ]
		);

		$starts = array_map( fn( $e ) => $e->getStartDate()->format( 'Y-m-d H:i:s' ), $result );
		$this->assertNotContains( '2026-01-19 09:00:00', $starts );
	}

	public function testCountRuleLimitsOccurrences(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=DAILY;COUNT=3' ),
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-12-31 23:59:59' )
		);

		$this->assertCount( 3, $result );
	}

	public function testInvalidRuleReturnsEmpty(): void
	{
		$expander = new RecurrenceExpander();
		$result = $expander->expand(
			$this->master( 'FREQ=BOGUS' ),
			new DateTimeImmutable( '2026-01-01 00:00:00' ),
			new DateTimeImmutable( '2026-12-31 23:59:59' )
		);

		$this->assertSame( [], $result );
	}
}
