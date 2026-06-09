<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Event;
use DateTimeImmutable;

/**
 * Repository interface for Event entities.
 *
 * @package Neuron\Cms\Repositories
 */
interface IEventRepository
{
	/**
	 * Get all events
	 *
	 * @return Event[]
	 */
	public function all(): array;

	/**
	 * Find event by ID
	 *
	 * @param int $id
	 * @return Event|null
	 */
	public function findById( int $id ): ?Event;

	/**
	 * Find event by slug
	 *
	 * @param string $slug
	 * @return Event|null
	 */
	public function findBySlug( string $slug ): ?Event;

	/**
	 * Get events by category
	 *
	 * @param int $categoryId
	 * @param string $status Filter by status (default: 'published')
	 * @return Event[]
	 */
	public function getByCategory( int $categoryId, string $status = 'published' ): array;

	/**
	 * Get upcoming events
	 *
	 * @param int|null $limit Maximum number of events to return
	 * @param string $status Filter by status (default: 'published')
	 * @return Event[]
	 */
	public function getUpcoming( ?int $limit = null, string $status = 'published' ): array;

	/**
	 * Get upcoming events within a single category
	 *
	 * @param int $categoryId
	 * @param int|null $limit Maximum number of events to return (default: 3)
	 * @param string $status Filter by status (default: 'published')
	 * @return Event[]
	 */
	public function getUpcomingByCategory( int $categoryId, ?int $limit = 3, string $status = 'published' ): array;

	/**
	 * Get past events
	 *
	 * @param int|null $limit Maximum number of events to return
	 * @param string $status Filter by status (default: 'published')
	 * @return Event[]
	 */
	public function getPast( ?int $limit = null, string $status = 'published' ): array;

	/**
	 * Get the next available featured event.
	 *
	 * Returns the soonest featured event that has not yet ended (ongoing or
	 * upcoming), or null when none is available.
	 *
	 * @param string $status Filter by status (default: 'published')
	 * @return Event|null
	 */
	public function getNextFeatured( string $status = 'published' ): ?Event;

	/**
	 * Get events by date range
	 *
	 * @param DateTimeImmutable $startDate
	 * @param DateTimeImmutable $endDate
	 * @param string $status Filter by status (default: 'published')
	 * @return Event[]
	 */
	public function getByDateRange( DateTimeImmutable $startDate, DateTimeImmutable $endDate, string $status = 'published' ): array;

	/**
	 * Get events by creator
	 *
	 * @param int $userId
	 * @return Event[]
	 */
	public function getByCreator( int $userId ): array;

	/**
	 * Create new event
	 *
	 * @param Event $event
	 * @return Event
	 */
	public function create( Event $event ): Event;

	/**
	 * Update event
	 *
	 * @param Event $event
	 * @return Event
	 */
	public function update( Event $event ): Event;

	/**
	 * Delete event
	 *
	 * @param Event $event
	 * @return bool
	 */
	public function delete( Event $event ): bool;

	/**
	 * Check if event slug exists (excluding a specific ID)
	 *
	 * @param string $slug
	 * @param int|null $excludeId
	 * @return bool
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * Increment view count for an event
	 *
	 * @param Event $event
	 * @return void
	 */
	public function incrementViewCount( Event $event ): void;
}
