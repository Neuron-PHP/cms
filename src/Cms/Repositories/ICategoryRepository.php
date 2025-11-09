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
	public function findById( int $Id ): ?Category;

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $Slug ): ?Category;

	/**
	 * Find category by name
	 */
	public function findByName( string $Name ): ?Category;

	/**
	 * Create a new category
	 */
	public function create( Category $Category ): Category;

	/**
	 * Update an existing category
	 */
	public function update( Category $Category ): bool;

	/**
	 * Delete a category
	 */
	public function delete( int $Id ): bool;

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
