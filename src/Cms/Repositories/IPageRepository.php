<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Page;

/**
 * Page repository interface.
 *
 * @package Neuron\Cms\Repositories
 */
interface IPageRepository
{
	/**
	 * Find page by ID
	 *
	 * @param int $id Page ID
	 * @return Page|null
	 */
	public function findById( int $id ): ?Page;

	/**
	 * Find page by slug
	 *
	 * @param string $slug Page slug
	 * @return Page|null
	 */
	public function findBySlug( string $slug ): ?Page;

	/**
	 * Create a new page
	 *
	 * @param Page $page Page to create
	 * @return Page Created page with ID
	 */
	public function create( Page $page ): Page;

	/**
	 * Update an existing page
	 *
	 * @param Page $page Page to update
	 * @return bool True if updated, false otherwise
	 */
	public function update( Page $page ): bool;

	/**
	 * Delete a page
	 *
	 * @param int $id Page ID
	 * @return bool True if deleted, false otherwise
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all pages
	 *
	 * @param string|null $status Filter by status (null for all)
	 * @param int $limit Maximum number of pages (0 for no limit)
	 * @param int $offset Offset for pagination
	 * @return Page[]
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array;

	/**
	 * Get published pages
	 *
	 * @param int $limit Maximum number of pages (0 for no limit)
	 * @param int $offset Offset for pagination
	 * @return Page[]
	 */
	public function getPublished( int $limit = 0, int $offset = 0 ): array;

	/**
	 * Get draft pages
	 *
	 * @return Page[]
	 */
	public function getDrafts(): array;

	/**
	 * Get pages by author
	 *
	 * @param int $authorId Author user ID
	 * @param string|null $status Filter by status (null for all)
	 * @return Page[]
	 */
	public function getByAuthor( int $authorId, ?string $status = null ): array;

	/**
	 * Count total pages
	 *
	 * @param string|null $status Filter by status (null for all)
	 * @return int
	 */
	public function count( ?string $status = null ): int;

	/**
	 * Increment page view count
	 *
	 * @param int $id Page ID
	 * @return bool True if updated, false otherwise
	 */
	public function incrementViewCount( int $id ): bool;
}
