<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\EventCategory;

/**
 * Repository interface for EventCategory entities.
 *
 * @package Neuron\Cms\Repositories
 */
interface IEventCategoryRepository
{
	/**
	 * Get all event categories
	 *
	 * @return EventCategory[]
	 */
	public function all(): array;

	/**
	 * Find category by ID
	 *
	 * @param int $id
	 * @return EventCategory|null
	 */
	public function findById( int $id ): ?EventCategory;

	/**
	 * Find category by slug
	 *
	 * @param string $slug
	 * @return EventCategory|null
	 */
	public function findBySlug( string $slug ): ?EventCategory;

	/**
	 * Find categories by IDs
	 *
	 * @param array $ids
	 * @return EventCategory[]
	 */
	public function findByIds( array $ids ): array;

	/**
	 * Create new category
	 *
	 * @param EventCategory $category
	 * @return EventCategory
	 */
	public function create( EventCategory $category ): EventCategory;

	/**
	 * Update category
	 *
	 * @param EventCategory $category
	 * @return EventCategory
	 */
	public function update( EventCategory $category ): EventCategory;

	/**
	 * Delete category
	 *
	 * @param EventCategory $category
	 * @return bool
	 */
	public function delete( EventCategory $category ): bool;

	/**
	 * Check if category slug exists (excluding a specific ID)
	 *
	 * @param string $slug
	 * @param int|null $excludeId
	 * @return bool
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool;
}
