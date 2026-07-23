<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Dto\Dto;

/**
 * Event updater service interface
 *
 * @package Neuron\Cms\Services\Event
 */
interface IEventUpdater
{
	/**
	 * Update an existing event from DTO
	 *
	 * @param Dto $request DTO containing event data
	 * @return Event
	 */
	public function update( Dto $request ): Event;

	/**
	 * Cancel a single occurrence of a recurring series.
	 *
	 * @param int $eventId Master event id
	 * @param string $occurrenceDate Occurrence start (datetime or Y-m-d)
	 * @return void
	 */
	public function cancelOccurrence( int $eventId, string $occurrenceDate ): void;

	/**
	 * Restore a previously cancelled occurrence.
	 *
	 * @param int $eventId Master event id
	 * @param string $occurrenceDate Occurrence start (datetime or Y-m-d)
	 * @return void
	 */
	public function restoreOccurrence( int $eventId, string $occurrenceDate ): void;

	/**
	 * List upcoming series occurrences for admin management.
	 *
	 * @param int $eventId Master event id
	 * @param int $limit
	 * @return array<int, array{occurrence: \DateTimeImmutable, cancelled: bool, value: string}>
	 */
	public function listOccurrences( int $eventId, int $limit = 40 ): array;
}
