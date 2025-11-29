<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;

/**
 * Post update service.
 *
 * Updates existing posts and their relationships.
 *
 * @package Neuron\Cms\Services\Post
 */
class Updater
{
	private IPostRepository $_postRepository;
	private ICategoryRepository $_categoryRepository;
	private TagResolver $_tagResolver;

	public function __construct(
		IPostRepository $postRepository,
		ICategoryRepository $categoryRepository,
		TagResolver $tagResolver
	)
	{
		$this->_postRepository = $postRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_tagResolver = $tagResolver;
	}

	/**
	 * Update an existing post
	 *
	 * @param Post $post The post to update
	 * @param string $title Post title
	 * @param string $content Editor.js JSON content
	 * @param string $status Post status
	 * @param string|null $slug Custom slug
	 * @param string|null $excerpt Excerpt
	 * @param string|null $featuredImage Featured image URL
	 * @param array $categoryIds Array of category IDs
	 * @param string $tagNames Comma-separated tag names
	 * @return Post
	 */
	public function update(
		Post $post,
		string $title,
		string $content,
		string $status,
		?string $slug = null,
		?string $excerpt = null,
		?string $featuredImage = null,
		array $categoryIds = [],
		string $tagNames = ''
	): Post
	{
		$post->setTitle( $title );
		$post->setSlug( $slug ?: $this->generateSlug( $title ) );
		$post->setContent( $content );
		$post->setExcerpt( $excerpt );
		$post->setFeaturedImage( $featuredImage );
		$post->setStatus( $status );

		// Business rule: auto-set published date when changing to published status
		if( $status === Post::STATUS_PUBLISHED && !$post->getPublishedAt() )
		{
			$post->setPublishedAt( new \DateTimeImmutable() );
		}

		// Update relationships
		$categories = $this->_categoryRepository->findByIds( $categoryIds );
		$post->setCategories( $categories );

		$tags = $this->_tagResolver->resolveFromString( $tagNames );
		$post->setTags( $tags );

		$this->_postRepository->update( $post );

		return $post;
	}

	/**
	 * Generate URL-friendly slug from title
	 *
	 * For titles with only non-ASCII characters (e.g., "你好", "مرحبا"),
	 * generates a fallback slug using uniqid().
	 *
	 * @param string $title
	 * @return string
	 */
	private function generateSlug( string $title ): string
	{
		$slug = strtolower( trim( $title ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		// Fallback for titles with no ASCII characters
		if( $slug === '' )
		{
			$slug = 'post-' . uniqid();
		}

		return $slug;
	}
}
