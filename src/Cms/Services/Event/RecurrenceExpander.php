<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Log\Log;
use DateInterval;
use DateTimeImmutable;

/**
 * Expands a recurring master event into concrete occurrences for a date range.
 *
 * Occurrences are generated on the fly: each occurrence is a clone of the
 * master with its start/end shifted to the occurrence date (the duration of
 * the master is preserved) and tagged via setOccurrenceDate(). Excluded dates
 * (cancellations / EXDATE) and dates that have a stored override row are
 * skipped so the caller can add overrides separately.
 *
 * @package Neuron\Cms\Services\Event
 */
class RecurrenceExpander
{
	/**
	 * Expand a master event into occurrences whose start falls within the range.
	 *
	 * When the master is not recurring it is returned as-is (when it overlaps
	 * the range), so callers can use a single code path.
	 *
	 * @param Event $master The recurring master (or a plain event)
	 * @param DateTimeImmutable $rangeStart Inclusive range start
	 * @param DateTimeImmutable $rangeEnd Inclusive range end
	 * @param array<int, string> $excludedDates Occurrence starts to skip ('Y-m-d H:i:s')
	 * @param array<int, string> $overriddenDates Occurrence starts handled by overrides ('Y-m-d H:i:s')
	 * @return Event[] Generated occurrences ordered by start ascending
	 */
	public function expand(
		Event $master,
		DateTimeImmutable $rangeStart,
		DateTimeImmutable $rangeEnd,
		array $excludedDates = [],
		array $overriddenDates = []
	): array
	{
		if( !$master->isRecurring() )
		{
			return $this->overlaps( $master, $rangeStart, $rangeEnd ) ? [ $master ] : [];
		}

		try
		{
			$rule = RecurrenceRule::create( $master->getRrule(), $master->getStartDate() );
		}
		catch( \Throwable $e )
		{
			Log::warning( 'RecurrenceExpander: invalid rule for event ' . $master->getId() . ': ' . $e->getMessage() );
			return [];
		}

		$duration = $this->duration( $master );
		$skip = array_flip( array_merge( array_values( $excludedDates ), array_values( $overriddenDates ) ) );

		$occurrences = [];

		foreach( $rule->getOccurrencesBetween( $rangeStart, $rangeEnd ) as $occurrence )
		{
			$start = DateTimeImmutable::createFromInterface( $occurrence );
			$key = $start->format( 'Y-m-d H:i:s' );

			if( isset( $skip[ $key ] ) )
			{
				continue;
			}

			$occurrences[] = $this->buildOccurrence( $master, $start, $duration );
		}

		return $occurrences;
	}

	/**
	 * Build a single occurrence clone of the master at the given start.
	 *
	 * @param Event $master
	 * @param DateTimeImmutable $start
	 * @param DateInterval|null $duration
	 * @return Event
	 */
	public function buildOccurrence( Event $master, DateTimeImmutable $start, ?DateInterval $duration ): Event
	{
		$occurrence = clone $master;
		$occurrence->setStartDate( $start );
		$occurrence->setEndDate( $duration !== null ? $start->add( $duration ) : null );
		$occurrence->setOccurrenceDate( $start );

		return $occurrence;
	}

	/**
	 * Duration between the master start and end, or null when the master has no
	 * end date.
	 *
	 * @param Event $master
	 * @return DateInterval|null
	 */
	public function duration( Event $master ): ?DateInterval
	{
		$end = $master->getEndDate();

		if( $end === null )
		{
			return null;
		}

		return $master->getStartDate()->diff( $end );
	}

	/**
	 * Whether a (non-recurring) event overlaps the given range.
	 *
	 * @param Event $event
	 * @param DateTimeImmutable $rangeStart
	 * @param DateTimeImmutable $rangeEnd
	 * @return bool
	 */
	private function overlaps( Event $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd ): bool
	{
		$start = $event->getStartDate();
		$end = $event->getEndDate() ?? $start;

		return $start <= $rangeEnd && $end >= $rangeStart;
	}
}
