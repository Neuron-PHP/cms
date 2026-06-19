<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\RecurrenceRule;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class RecurrenceRuleTest extends TestCase
{
	public function testCompileReturnsNullForNone(): void
	{
		$this->assertNull( RecurrenceRule::compile( [ 'freq' => 'none' ] ) );
		$this->assertNull( RecurrenceRule::compile( [] ) );
	}

	public function testCompileDailyWithInterval(): void
	{
		$rrule = RecurrenceRule::compile( [ 'freq' => 'daily', 'interval' => 3 ] );

		$this->assertSame( 'FREQ=DAILY;INTERVAL=3', $rrule );
	}

	public function testCompileWeeklyWithByDay(): void
	{
		$rrule = RecurrenceRule::compile( [
			'freq'  => 'weekly',
			'byday' => 'fr,mo,we'
		] );

		// BYDAY is normalised to canonical weekday order.
		$this->assertSame( 'FREQ=WEEKLY;BYDAY=MO,WE,FR', $rrule );
	}

	public function testCompileWithCount(): void
	{
		$rrule = RecurrenceRule::compile( [ 'freq' => 'weekly', 'end' => 'count', 'count' => 5 ] );

		$this->assertSame( 'FREQ=WEEKLY;COUNT=5', $rrule );
	}

	public function testCompileWithUntil(): void
	{
		$rrule = RecurrenceRule::compile( [
			'freq'  => 'daily',
			'end'   => 'until',
			'until' => '2026-01-10'
		] );

		$this->assertStringContainsString( 'FREQ=DAILY', $rrule );
		$this->assertStringContainsString( 'UNTIL=20260110T000000Z', $rrule );
	}

	public function testComputeUntilForCountRule(): void
	{
		$start = new DateTimeImmutable( '2026-01-01 10:00:00' );
		$until = RecurrenceRule::computeUntil( 'FREQ=DAILY;COUNT=3', $start );

		$this->assertNotNull( $until );
		$this->assertSame( '2026-01-03 10:00:00', $until->format( 'Y-m-d H:i:s' ) );
	}

	public function testComputeUntilIsNullForInfiniteRule(): void
	{
		$start = new DateTimeImmutable( '2026-01-01 10:00:00' );

		$this->assertNull( RecurrenceRule::computeUntil( 'FREQ=WEEKLY', $start ) );
	}

	public function testWithUntilReplacesExistingBound(): void
	{
		$result = RecurrenceRule::withUntil(
			'FREQ=DAILY;COUNT=10',
			new DateTimeImmutable( '2026-02-01 09:00:00' )
		);

		$this->assertStringNotContainsString( 'COUNT', $result );
		$this->assertStringContainsString( 'UNTIL=20260201T090000Z', $result );
		$this->assertStringContainsString( 'FREQ=DAILY', $result );
	}

	public function testStripBoundRemovesEndConditions(): void
	{
		$this->assertSame( 'FREQ=WEEKLY;INTERVAL=2', RecurrenceRule::stripBound( 'FREQ=WEEKLY;INTERVAL=2;UNTIL=20260201T090000Z' ) );
		$this->assertSame( 'FREQ=DAILY', RecurrenceRule::stripBound( 'FREQ=DAILY;COUNT=5' ) );
	}

	public function testIsValidDetectsBadRule(): void
	{
		$start = new DateTimeImmutable( '2026-01-01 10:00:00' );

		$this->assertTrue( RecurrenceRule::isValid( 'FREQ=DAILY', $start ) );
		$this->assertFalse( RecurrenceRule::isValid( 'FREQ=NONSENSE', $start ) );
	}
}
