<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use DateTimeImmutable;

/**
 * Post creation service.
 *
 * Creates new posts with categories and tags relationships.
 *
 * @package Neuron\Cms\Services\Post
 */
class Creator
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
	 * Create a new post
	 *
	 * @param string $title Post title
	 * @param string $body Post body content
	 * @param int $authorId Author user ID
	 * @param string $status Post status (draft, published, scheduled)
	 * @param string|null $slug Optional custom slug (auto-generated if not provided)
	 * @param string|null $excerpt Optional excerpt
	 * @param string|null $featuredImage Optional featured image URL
	 * @param array $categoryIds Array of category IDs
	 * @param string $tagNames Comma-separated tag names
	 * @return Post
	 */
	public function create(
		string $title,
		string $body,
		int $authorId,
		string $status,
		?string $slug = null,
		?string $excerpt = null,
		?string $featuredImage = null,
		array $categoryIds = [],
		string $tagNames = ''
	): Post
	{
		$post = new Post();
		$post->setTitle( $title );
		$post->setSlug( $slug ?: $this->generateSlug( $title ) );
		$post->setBody( $body );
		$post->setExcerpt( $excerpt );
		$post->setFeaturedImage( $featuredImage );
		$post->setAuthorId( $authorId );
		$post->setStatus( $status );
		$post->setCreatedAt( new DateTimeImmutable() );

		// Business rule: auto-set published date for published posts
		if( $status === Post::STATUS_PUBLISHED )
		{
			$post->setPublishedAt( new DateTimeImmutable() );
		}

		// Attach categories
		$categories = $this->_categoryRepository->findByIds( $categoryIds );
		$post->setCategories( $categories );

		// Resolve and attach tags
		$tags = $this->_tagResolver->resolveFromString( $tagNames );
		$post->setTags( $tags );

		return $this->_postRepository->create( $post );
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
