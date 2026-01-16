<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;
use Neuron\Cms\Enums\ContentStatus;

/**
 * Post update service.
 *
 * Updates existing posts and their relationships.
 *
 * @package Neuron\Cms\Services\Post
 */
class Updater implements IPostUpdater
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
	 * Update an existing post from DTO
	 *
	 * @param Dto $request DTO containing id, title, content, status, slug, excerpt, featured_image
	 * @param array $categoryIds Array of category IDs
	 * @param string $tagNames Comma-separated tag names
	 * @return Post
	 * @throws \Exception If post not found
	 */
	public function update( Dto $request, array $categoryIds = [], string $tagNames = '' ): Post
	{
		// Extract values from DTO
		$id = $request->id;
		$title = $request->title;
		$content = $request->content;
		$status = $request->status;
		$slug = $request->slug ?? null;
		$excerpt = $request->excerpt ?? null;
		$featuredImage = $request->featured_image ?? null;
		$publishedAt = $request->published_at ?? null;

		// Look up the post
		$post = $this->_postRepository->findById( $id );
		if( !$post )
		{
			throw new \Exception( "Post with ID {$id} not found" );
		}

		$post->setTitle( $title );
		$post->setSlug( $slug ?: $this->generateSlug( $title ) );
		$post->setContent( $content );
		$post->setExcerpt( $excerpt );
		$post->setFeaturedImage( $featuredImage );
		$post->setStatus( $status );

		// Business rule: set published date
		if( $publishedAt && trim( $publishedAt ) !== '' )
		{
			// Use provided published date
			$post->setPublishedAt( new \DateTimeImmutable( $publishedAt ) );
		}
		elseif( $status === ContentStatus::PUBLISHED->value && !$post->getPublishedAt() )
		{
			// Auto-set to now for published posts when not provided and not already set
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
