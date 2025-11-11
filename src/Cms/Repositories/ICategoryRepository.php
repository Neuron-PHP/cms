<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Category;

/**
 * Category repository interface.
 *
 * @package Neuron\Cms\Repositories
 */
interface ICategoryRepository
{
	/**
	 * Find category by ID
	 */
	public function findById( int $id ): ?Category;

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $slug ): ?Category;

	/**
	 * Find category by name
	 */
	public function findByName( string $name ): ?Category;

	/**
	 * Find multiple categories by IDs
	 *
	 * @param array $ids Array of category IDs
	 * @return Category[]
	 */
	public function findByIds( array $ids ): array;

	/**
	 * Create a new category
	 */
	public function create( Category $category ): Category;

	/**
	 * Update an existing category
	 */
	public function update( Category $category ): bool;

	/**
	 * Delete a category
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all categories
	 *
	 * @return Category[]
	 */
	public function all(): array;

	/**
	 * Count total categories
	 */
	public function count(): int;

	/**
	 * Get categories with post count
	 *
	 * @return array Array of ['category' => Category, 'post_count' => int]
	 */
	public function allWithPostCount(): array;
}
