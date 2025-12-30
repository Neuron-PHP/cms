<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;

/**
 * Post deleter service interface
 *
 * @package Neuron\Cms\Services\Post
 */
interface IPostDeleter
{
	/**
	 * Delete a post
	 *
	 * @param Post $post Post to delete
	 * @return bool True if deleted successfully
	 */
	public function delete( Post $post ): bool;

	/**
	 * Delete a post by ID
	 *
	 * @param int $id Post ID to delete
	 * @return bool True if deleted successfully
	 */
	public function deleteById( int $id ): bool;
}
