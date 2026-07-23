<?php

namespace Neuron\Cms\Services\Event;

use DateTimeImmutable;
use DateTimeInterface;
use RRule\RRule;

/**
 * Helpers for compiling, validating and inspecting RFC 5545 recurrence rules.
 *
 * The CMS stores a recurrence rule as an RRULE string without DTSTART (the
 * event's start_date is the DTSTART). This class centralises construction of
 * the rlanvin RRule object so the expander, services and tests share one
 * implementation.
 *
 * @package Neuron\Cms\Services\Event
 */
class RecurrenceRule
{
	/**
	 * Supported frequencies mapped to their RFC token.
	 */
	private const FREQUENCIES = [
		'daily'   => 'DAILY',
		'weekly'  => 'WEEKLY',
		'monthly' => 'MONTHLY',
		'yearly'  => 'YEARLY'
	];

	/**
	 * Valid BYDAY weekday tokens.
	 */
	private const WEEKDAYS = [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ];

	/**
	 * Build an rlanvin RRule object from a stored rule string and start date.
	 *
	 * @param string $rrule RRULE string (without DTSTART)
	 * @param DateTimeInterface $dtstart Series start (DTSTART)
	 * @return RRule
	 * @throws \InvalidArgumentException when the rule is invalid
	 */
	public static function create( string $rrule, DateTimeInterface $dtstart ): RRule
	{
		return new RRule( $rrule, $dtstart );
	}

	/**
	 * Whether the given date is an occurrence of the rule.
	 *
	 * Implemented via getOccurrencesBetween rather than the library's
	 * occursAt(), which mutates a DateTimeImmutable internally and triggers a
	 * fatal error on PHP 8.5+.
	 *
	 * @param string $rrule
	 * @param DateTimeInterface $dtstart
	 * @param DateTimeInterface $date
	 * @return bool
	 */
	public static function occursAt( string $rrule, DateTimeInterface $dtstart, DateTimeInterface $date ): bool
	{
		try
		{
			$rule = self::create( $rrule, $dtstart );
		}
		catch( \Throwable $e )
		{
			return false;
		}

		return !empty( $rule->getOccurrencesBetween( $date, $date ) );
	}

	/**
	 * Validate a recurrence rule string against a start date.
	 *
	 * @param string $rrule
	 * @param DateTimeInterface $dtstart
	 * @return bool
	 */
	public static function isValid( string $rrule, DateTimeInterface $dtstart ): bool
	{
		try
		{
			self::create( $rrule, $dtstart );
			return true;
		}
		catch( \Throwable $e )
		{
			return false;
		}
	}

	/**
	 * Compile structured recurrence fields into an RRULE string.
	 *
	 * Returns null when the event does not repeat (frequency 'none' or empty).
	 *
	 * Recognised keys:
	 *   freq           one of none|daily|weekly|monthly|yearly
	 *   interval       positive integer (default 1)
	 *   byday          comma-separated weekday tokens (e.g. "MO,WE,FR")
	 *                  or a monthly ordinal token (e.g. "1SA", "-1FR")
	 *   monthly_mode   for monthly: "day" (same calendar day) or "weekday"
	 *                  (nth weekday, e.g. first Saturday)
	 *   month_ordinal  1|2|3|4|-1 when monthly_mode = weekday
	 *   month_weekday  MO|TU|WE|TH|FR|SA|SU when monthly_mode = weekday
	 *   end            one of never|until|count
	 *   until          end date (Y-m-d or any parseable date) when end = until
	 *   count          occurrence count when end = count
	 *
	 * @param array<string, mixed> $parts
	 * @return string|null
	 */
	public static function compile( array $parts ): ?string
	{
		$freq = strtolower( trim( (string)( $parts['freq'] ?? 'none' ) ) );

		if( $freq === '' || $freq === 'none' || !isset( self::FREQUENCIES[ $freq ] ) )
		{
			return null;
		}

		$segments = [ 'FREQ=' . self::FREQUENCIES[ $freq ] ];

		$interval = (int)( $parts['interval'] ?? 1 );
		if( $interval > 1 )
		{
			$segments[] = 'INTERVAL=' . $interval;
		}

		if( $freq === 'weekly' )
		{
			$byday = self::normalizeByDay( $parts['byday'] ?? '' );
			if( $byday !== '' )
			{
				$segments[] = 'BYDAY=' . $byday;
			}
		}
		elseif( $freq === 'monthly' )
		{
			$monthlyByDay = self::resolveMonthlyByDay( $parts );
			if( $monthlyByDay !== '' )
			{
				$segments[] = 'BYDAY=' . $monthlyByDay;
			}
		}

		$end = strtolower( trim( (string)( $parts['end'] ?? 'never' ) ) );

		if( $end === 'until' && !empty( $parts['until'] ) )
		{
			$until = new DateTimeImmutable( (string)$parts['until'] );
			$segments[] = 'UNTIL=' . $until->format( 'Ymd\THis\Z' );
		}
		elseif( $end === 'count' && (int)( $parts['count'] ?? 0 ) > 0 )
		{
			$segments[] = 'COUNT=' . (int)$parts['count'];
		}

		return implode( ';', $segments );
	}

	/**
	 * Compute the last occurrence (UNTIL) for a bounded rule.
	 *
	 * Returns null for an infinite rule (no COUNT/UNTIL). Used to cache
	 * recurrence_until so range queries can skip non-intersecting masters.
	 *
	 * @param string $rrule
	 * @param DateTimeInterface $dtstart
	 * @return DateTimeImmutable|null
	 */
	public static function computeUntil( string $rrule, DateTimeInterface $dtstart ): ?DateTimeImmutable
	{
		$rule = self::create( $rrule, $dtstart );

		if( $rule->isInfinite() )
		{
			return null;
		}

		$occurrences = $rule->getOccurrences();
		$last = end( $occurrences );

		if( $last === false )
		{
			return null;
		}

		return DateTimeImmutable::createFromInterface( $last );
	}

	/**
	 * Return the rule with any existing UNTIL/COUNT replaced by an UNTIL bound.
	 *
	 * @param string $rrule
	 * @param DateTimeInterface $until
	 * @return string
	 */
	public static function withUntil( string $rrule, DateTimeInterface $until ): string
	{
		$segments = self::stripBoundSegments( $rrule );
		$segments[] = 'UNTIL=' . $until->format( 'Ymd\THis\Z' );

		return implode( ';', $segments );
	}

	/**
	 * Return the rule with any UNTIL/COUNT end condition removed (infinite).
	 *
	 * @param string $rrule
	 * @return string
	 */
	public static function stripBound( string $rrule ): string
	{
		return implode( ';', self::stripBoundSegments( $rrule ) );
	}

	/**
	 * Split a rule into segments, dropping UNTIL and COUNT.
	 *
	 * @param string $rrule
	 * @return array<int, string>
	 */
	private static function stripBoundSegments( string $rrule ): array
	{
		$segments = [];

		foreach( explode( ';', $rrule ) as $segment )
		{
			$segment = trim( $segment );
			if( $segment === '' )
			{
				continue;
			}

			$key = strtoupper( strtok( $segment, '=' ) );
			if( $key === 'UNTIL' || $key === 'COUNT' )
			{
				continue;
			}

			$segments[] = $segment;
		}

		return $segments;
	}

	/**
	 * Resolve a monthly BYDAY token for "nth weekday of the month" rules.
	 *
	 * Prefers month_ordinal + month_weekday when monthly_mode is weekday;
	 * otherwise accepts an ordinal token in byday (e.g. "1SA").
	 *
	 * @param array<string, mixed> $parts
	 * @return string Empty when the month should use the calendar day instead
	 */
	private static function resolveMonthlyByDay( array $parts ): string
	{
		$mode = strtolower( trim( (string)( $parts['monthly_mode'] ?? '' ) ) );

		if( $mode === 'day' )
		{
			return '';
		}

		if( $mode === 'weekday' )
		{
			$ordinal = trim( (string)( $parts['month_ordinal'] ?? '' ) );
			$weekday = strtoupper( trim( (string)( $parts['month_weekday'] ?? '' ) ) );

			if( $ordinal !== '' && $weekday !== '' )
			{
				return self::normalizeMonthlyByDay( $ordinal . $weekday );
			}

			return self::normalizeMonthlyByDay( $parts['byday'] ?? '' );
		}

		// Mode omitted: infer weekday mode from an ordinal BYDAY token.
		return self::normalizeMonthlyByDay( $parts['byday'] ?? '' );
	}

	/**
	 * Normalise monthly ordinal BYDAY tokens (e.g. 1SA, -1FR).
	 *
	 * @param mixed $byday
	 * @return string
	 */
	private static function normalizeMonthlyByDay( mixed $byday ): string
	{
		if( is_array( $byday ) )
		{
			$tokens = $byday;
		}
		else
		{
			$tokens = explode( ',', (string)$byday );
		}

		$valid = [];

		foreach( $tokens as $token )
		{
			$token = strtoupper( trim( (string)$token ) );

			if( preg_match( '/^(-?[1-4])(' . implode( '|', self::WEEKDAYS ) . ')$/', $token, $matches ) !== 1 )
			{
				continue;
			}

			$normalised = $matches[1] . $matches[2];

			if( !in_array( $normalised, $valid, true ) )
			{
				$valid[] = $normalised;
			}
		}

		return implode( ',', $valid );
	}

	/**
	 * Normalise a BYDAY value (string or array) into a comma-separated token
	 * list of valid weekdays, preserving RFC order.
	 *
	 * @param mixed $byday
	 * @return string
	 */
	private static function normalizeByDay( mixed $byday ): string
	{
		if( is_array( $byday ) )
		{
			$tokens = $byday;
		}
		else
		{
			$tokens = explode( ',', (string)$byday );
		}

		$valid = [];
		foreach( $tokens as $token )
		{
			$token = strtoupper( trim( (string)$token ) );
			if( in_array( $token, self::WEEKDAYS, true ) && !in_array( $token, $valid, true ) )
			{
				$valid[] = $token;
			}
		}

		// Preserve canonical weekday order for stable output.
		$ordered = array_values( array_filter( self::WEEKDAYS, fn( $d ) => in_array( $d, $valid, true ) ) );

		return implode( ',', $ordered );
	}
}
