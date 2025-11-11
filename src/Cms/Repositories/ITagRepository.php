<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Tag;

/**
 * Tag repository interface.
 *
 * @package Neuron\Cms\Repositories
 */
interface ITagRepository
{
	/**
	 * Find tag by ID
	 */
	public function findById( int $id ): ?Tag;

	/**
	 * Find tag by slug
	 */
	public function findBySlug( string $slug ): ?Tag;

	/**
	 * Find tag by name
	 */
	public function findByName( string $name ): ?Tag;

	/**
	 * Create a new tag
	 */
	public function create( Tag $tag ): Tag;

	/**
	 * Update an existing tag
	 */
	public function update( Tag $tag ): bool;

	/**
	 * Delete a tag
	 */
	public function delete( int $id ): bool;

	/**
	 * Get all tags
	 *
	 * @return Tag[]
	 */
	public function all(): array;

	/**
	 * Count total tags
	 */
	public function count(): int;

	/**
	 * Get tags with post count
	 *
	 * @return array Array of ['tag' => Tag, 'post_count' => int]
	 */
	public function allWithPostCount(): array;
}
