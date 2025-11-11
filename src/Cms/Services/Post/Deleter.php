<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;

/**
 * Post deletion service.
 *
 * Handles safe deletion of posts.
 *
 * @package Neuron\Cms\Services\Post
 */
class Deleter
{
	private IPostRepository $_postRepository;

	public function __construct( IPostRepository $postRepository )
	{
		$this->_postRepository = $postRepository;
	}

	/**
	 * Delete a post
	 *
	 * @param Post $post The post to delete
	 * @return bool True if deleted successfully
	 */
	public function delete( Post $post ): bool
	{
		if( !$post->getId() )
		{
			return false;
		}

		return $this->_postRepository->delete( $post->getId() );
	}

	/**
	 * Delete a post by ID
	 *
	 * @param int $id Post ID
	 * @return bool True if deleted successfully
	 */
	public function deleteById( int $id ): bool
	{
		return $this->_postRepository->delete( $id );
	}
}
