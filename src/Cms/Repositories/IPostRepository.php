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
	public function findById( int $id ): ?Post;

	/**
	 * Find post by slug
	 */
	public function findBySlug( string $slug ): ?Post;

	/**
	 * Create a new post
	 */
	public function create( Post $post ): Post;

	/**
	 * Update an existing post
	 */
	public function update( Post $post ): bool;

	/**
	 * Delete a post
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all posts
	 *
	 * @param string|null $status Filter by status (published, draft, scheduled)
	 * @param int $limit Limit number of results (0 = no limit)
	 * @param int $offset Offset for pagination
	 * @return Post[]
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array;

	/**
	 * Get posts by author
	 *
	 * @param int $authorId Author user ID
	 * @param string|null $status Filter by status
	 * @return Post[]
	 */
	public function getByAuthor( int $authorId, ?string $status = null ): array;

	/**
	 * Get posts by category
	 *
	 * @param int $categoryId Category ID
	 * @param string|null $status Filter by status
	 * @return Post[]
	 */
	public function getByCategory( int $categoryId, ?string $status = null ): array;

	/**
	 * Get posts by tag
	 *
	 * @param int $tagId Tag ID
	 * @param string|null $status Filter by status
	 * @return Post[]
	 */
	public function getByTag( int $tagId, ?string $status = null ): array;

	/**
	 * Get published posts
	 *
	 * @param int $limit Limit number of results (0 = no limit)
	 * @param int $offset Offset for pagination
	 * @return Post[]
	 */
	public function getPublished( int $limit = 0, int $offset = 0 ): array;

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
	 * @param string|null $status Filter by status
	 */
	public function count( ?string $status = null ): int;

	/**
	 * Increment post view count
	 */
	public function incrementViewCount( int $id ): bool;

	/**
	 * Attach categories to post
	 *
	 * @param int $postId Post ID
	 * @param array $categoryIds Array of category IDs
	 */
	public function attachCategories( int $postId, array $categoryIds ): bool;

	/**
	 * Detach all categories from post
	 */
	public function detachCategories( int $postId ): bool;

	/**
	 * Attach tags to post
	 *
	 * @param int $postId Post ID
	 * @param array $tagIds Array of tag IDs
	 */
	public function attachTags( int $postId, array $tagIds ): bool;

	/**
	 * Detach all tags from post
	 */
	public function detachTags( int $postId ): bool;
}
