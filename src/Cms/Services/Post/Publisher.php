<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use DateTimeImmutable;
use Exception;
use Neuron\Cms\Enums\ContentStatus;

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
	 * @throws Exception if post is already published or persistence fails
	 */
	public function publish( Post $post ): Post
	{
		// Validate post isn't already published
		if( $post->getStatus() === ContentStatus::PUBLISHED->value )
		{
			throw new Exception( 'Post is already published' );
		}

		$post->setStatus( ContentStatus::PUBLISHED->value );
		$post->setPublishedAt( new DateTimeImmutable() );

		if( !$this->_postRepository->update( $post ) )
		{
			throw new Exception( 'Failed to persist post changes' );
		}

		return $post;
	}

	/**
	 * Unpublish a post (revert to draft)
	 *
	 * @param Post $post The post to unpublish
	 * @return Post
	 * @throws Exception if post is not published or persistence fails
	 */
	public function unpublish( Post $post ): Post
	{
		// Validate post is currently published
		if( $post->getStatus() !== ContentStatus::PUBLISHED->value )
		{
			throw new Exception( 'Post is not currently published' );
		}

		$post->setStatus( ContentStatus::DRAFT->value );
		$post->setPublishedAt( null );

		if( !$this->_postRepository->update( $post ) )
		{
			throw new Exception( 'Failed to persist post changes' );
		}

		return $post;
	}

	/**
	 * Schedule a post for future publication
	 *
	 * @param Post $post The post to schedule
	 * @param DateTimeImmutable $publishAt When to publish
	 * @return Post
	 * @throws Exception if scheduled date is in the past or persistence fails
	 */
	public function schedule( Post $post, DateTimeImmutable $publishAt ): Post
	{
		// Validate scheduled date is in the future
		if( $publishAt <= new DateTimeImmutable() )
		{
			throw new Exception( 'Scheduled publish date must be in the future' );
		}

		$post->setStatus( ContentStatus::SCHEDULED->value );
		$post->setPublishedAt( $publishAt );

		if( !$this->_postRepository->update( $post ) )
		{
			throw new Exception( 'Failed to persist post changes' );
		}

		return $post;
	}
}
