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
	public function findById( int $Id ): ?Tag;

	/**
	 * Find tag by slug
	 */
	public function findBySlug( string $Slug ): ?Tag;

	/**
	 * Find tag by name
	 */
	public function findByName( string $Name ): ?Tag;

	/**
	 * Create a new tag
	 */
	public function create( Tag $Tag ): Tag;

	/**
	 * Update an existing tag
	 */
	public function update( Tag $Tag ): bool;

	/**
	 * Delete a tag
	 */
	public function delete( int $Id ): bool;

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
