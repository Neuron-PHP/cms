<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Post;

/**
 * Post repository interface.
 *
 * @package Neuron\Cms\Repositories
 */
interface IPostRepository
{
	/**
	 * Find post by ID
	 */
	public function findById( int $Id ): ?Post;

	/**
	 * Find post by slug
	 */
	public function findBySlug( string $Slug ): ?Post;

	/**
	 * Create a new post
	 */
	public function create( Post $Post ): Post;

	/**
	 * Update an existing post
	 */
	public function update( Post $Post ): bool;

	/**
	 * Delete a post
	 */
	public function delete( int $Id ): bool;

	/**
	 * Get all posts
	 *
	 * @param string|null $Status Filter by status (published, draft, scheduled)
	 * @param int $Limit Limit number of results (0 = no limit)
	 * @param int $Offset Offset for pagination
	 * @return Post[]
	 */
	public function all( ?string $Status = null, int $Limit = 0, int $Offset = 0 ): array;

	/**
	 * Get posts by author
	 *
	 * @param int $AuthorId Author user ID
	 * @param string|null $Status Filter by status
	 * @return Post[]
	 */
	public function getByAuthor( int $AuthorId, ?string $Status = null ): array;

	/**
	 * Get posts by category
	 *
	 * @param int $CategoryId Category ID
	 * @param string|null $Status Filter by status
	 * @return Post[]
	 */
	public function getByCategory( int $CategoryId, ?string $Status = null ): array;

	/**
	 * Get posts by tag
	 *
	 * @param int $TagId Tag ID
	 * @param string|null $Status Filter by status
	 * @return Post[]
	 */
	public function getByTag( int $TagId, ?string $Status = null ): array;

	/**
	 * Get published posts
	 *
	 * @param int $Limit Limit number of results (0 = no limit)
	 * @param int $Offset Offset for pagination
	 * @return Post[]
	 */
	public function getPublished( int $Limit = 0, int $Offset = 0 ): array;

	/**
	 * Get draft posts
	 *
	 * @return Post[]
	 */
	public function getDrafts(): array;

	/**
	 * Get scheduled posts
	 *
	 * @return Post[]
	 */
	public function getScheduled(): array;

	/**
	 * Count total posts
	 *
	 * @param string|null $Status Filter by status
	 */
	public function count( ?string $Status = null ): int;

	/**
	 * Increment post view count
	 */
	public function incrementViewCount( int $Id ): bool;

	/**
	 * Attach categories to post
	 *
	 * @param int $PostId Post ID
	 * @param array $CategoryIds Array of category IDs
	 */
	public function attachCategories( int $PostId, array $CategoryIds ): bool;

	/**
	 * Detach all categories from post
	 */
	public function detachCategories( int $PostId ): bool;

	/**
	 * Attach tags to post
	 *
	 * @param int $PostId Post ID
	 * @param array $TagIds Array of tag IDs
	 */
	public function attachTags( int $PostId, array $TagIds ): bool;

	/**
	 * Detach all tags from post
	 */
	public function detachTags( int $PostId ): bool;
}
