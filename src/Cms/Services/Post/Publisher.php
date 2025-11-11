<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use DateTimeImmutable;
use Exception;

/**
 * Post publishing service.
 *
 * Handles publishing draft posts.
 *
 * @package Neuron\Cms\Services\Post
 */
class Publisher
{
	private IPostRepository $_postRepository;

	public function __construct( IPostRepository $postRepository )
	{
		$this->_postRepository = $postRepository;
	}

	/**
	 * Publish a draft post
	 *
	 * @param Post $post The post to publish
	 * @return Post
	 * @throws Exception if post is already published
	 */
	public function publish( Post $post ): Post
	{
		// Validate post isn't already published
		if( $post->getStatus() === Post::STATUS_PUBLISHED )
		{
			throw new Exception( 'Post is already published' );
		}

		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setPublishedAt( new DateTimeImmutable() );

		$this->_postRepository->update( $post );

		return $post;
	}

	/**
	 * Unpublish a post (revert to draft)
	 *
	 * @param Post $post The post to unpublish
	 * @return Post
	 * @throws Exception if post is not published
	 */
	public function unpublish( Post $post ): Post
	{
		// Validate post is currently published
		if( $post->getStatus() !== Post::STATUS_PUBLISHED )
		{
			throw new Exception( 'Post is not currently published' );
		}

		$post->setStatus( Post::STATUS_DRAFT );
		$post->setPublishedAt( null );

		$this->_postRepository->update( $post );

		return $post;
	}

	/**
	 * Schedule a post for future publication
	 *
	 * @param Post $post The post to schedule
	 * @param DateTimeImmutable $publishAt When to publish
	 * @return Post
	 * @throws Exception if scheduled date is in the past
	 */
	public function schedule( Post $post, DateTimeImmutable $publishAt ): Post
	{
		// Validate scheduled date is in the future
		if( $publishAt <= new DateTimeImmutable() )
		{
			throw new Exception( 'Scheduled publish date must be in the future' );
		}

		$post->setStatus( Post::STATUS_SCHEDULED );
		$post->setPublishedAt( $publishAt );

		$this->_postRepository->update( $post );

		return $post;
	}
}
