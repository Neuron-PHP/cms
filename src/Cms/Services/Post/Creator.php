<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;
use DateTimeImmutable;
use Neuron\Cms\Enums\ContentStatus;

/**
 * Post creation service.
 *
 * Creates new posts with categories and tags relationships.
 *
 * @package Neuron\Cms\Services\Post
 */
class Creator implements IPostCreator
{
	private IPostRepository $_postRepository;
	private ICategoryRepository $_categoryRepository;
	private TagResolver $_tagResolver;
	private SlugGenerator $_slugGenerator;

	public function __construct(
		IPostRepository $postRepository,
		ICategoryRepository $categoryRepository,
		TagResolver $tagResolver,
		?SlugGenerator $slugGenerator = null
	)
	{
		$this->_postRepository = $postRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_tagResolver = $tagResolver;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
	}

	/**
	 * Create a new post from DTO
	 *
	 * @param Dto $request DTO containing title, content, author_id, status, slug, excerpt, featured_image
	 * @param array $categoryIds Array of category IDs
	 * @param string $tagNames Comma-separated tag names
	 * @return Post
	 */
	public function create( Dto $request, array $categoryIds = [], string $tagNames = '' ): Post
	{
		// Extract values from DTO
		$title = $request->title;
		$content = $request->content;
		$authorId = $request->author_id;
		$status = $request->status;
		$slug = $request->slug ?? null;
		$excerpt = $request->excerpt ?? null;
		$featuredImage = $request->featured_image ?? null;
		$publishedAt = $request->published_at ?? null;

		$post = new Post();
		$post->setTitle( $title );
		$post->setSlug( $slug ?: $this->generateSlug( $title ) );
		$post->setContent( $content );
		$post->setExcerpt( $excerpt );
		$post->setFeaturedImage( $featuredImage );
		$post->setAuthorId( $authorId );
		$post->setStatus( $status );
		$post->setCreatedAt( new DateTimeImmutable() );

		// Business rule: set published date
		if( $status === ContentStatus::SCHEDULED->value )
		{
			// Scheduled posts MUST have a published date
			if( !$publishedAt || trim( $publishedAt ) === '' )
			{
				throw new \InvalidArgumentException( 'Scheduled posts require a published date' );
			}
			$post->setPublishedAt( new DateTimeImmutable( $publishedAt ) );
		}
		elseif( $publishedAt && trim( $publishedAt ) !== '' )
		{
			// Use provided published date
			$post->setPublishedAt( new DateTimeImmutable( $publishedAt ) );
		}
		elseif( $status === ContentStatus::PUBLISHED->value )
		{
			// Auto-set to now for published posts when not provided
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
	 * generates a fallback slug using a unique identifier.
	 *
	 * @param string $title
	 * @return string
	 */
	private function generateSlug( string $title ): string
	{
		return $this->_slugGenerator->generate( $title, 'post' );
	}
}
