<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\EventRegistration;
use DateTimeImmutable;

/**
 * Repository interface for EventRegistration entities.
 *
 * @package Neuron\Cms\Repositories
 */
interface IEventRegistrationRepository
{
	/**
	 * Persist a registration and return it with its new id.
	 *
	 * @param EventRegistration $registration
	 * @return EventRegistration
	 */
	public function create( EventRegistration $registration ): EventRegistration;

	/**
	 * Find a registration by id.
	 *
	 * @param int $id
	 * @return EventRegistration|null
	 */
	public function findById( int $id ): ?EventRegistration;

	/**
	 * Get all registrations for an event, newest first.
	 *
	 * @param int $eventId
	 * @return EventRegistration[]
	 */
	public function getByEvent( int $eventId ): array;

	/**
	 * Count registrations for an event, optionally scoped to one occurrence.
	 *
	 * @param int $eventId
	 * @param DateTimeImmutable|null $occurrenceDate Occurrence to scope to (recurring events)
	 * @return int
	 */
	public function countByEvent( int $eventId, ?DateTimeImmutable $occurrenceDate = null ): int;

	/**
	 * Determine whether an email is already registered for an event
	 * (active registrations only), optionally scoped to one occurrence.
	 *
	 * @param int $eventId
	 * @param string $email
	 * @param DateTimeImmutable|null $occurrenceDate Occurrence to scope to (recurring events)
	 * @return bool
	 */
	public function existsForEmail( int $eventId, string $email, ?DateTimeImmutable $occurrenceDate = null ): bool;

	/**
	 * Return a page of registrations, newest first, optionally filtered by event.
	 *
	 * @param int $page Page number (1-based)
	 * @param int $perPage Items per page
	 * @param int|null $eventId Optional event filter
	 * @return array{items: EventRegistration[], total: int, page: int, per_page: int, pages: int}
	 */
	public function paginate( int $page = 1, int $perPage = 25, ?int $eventId = null ): array;

	/**
	 * Delete a registration by id.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool;
}
