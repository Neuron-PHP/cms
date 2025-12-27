<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Core\System\IRandom;
use Neuron\Core\System\RealRandom;
use DateTimeImmutable;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Enums\PageTemplate;

/**
 * Page creation service.
 *
 * Creates new pages with validation and slug generation.
 *
 * @package Neuron\Cms\Services\Page
 */
class Creator
{
	private IPageRepository $_pageRepository;
	private IRandom $_random;

	public function __construct( IPageRepository $pageRepository, ?IRandom $random = null )
	{
		$this->_pageRepository = $pageRepository;
		$this->_random = $random ?? new RealRandom();
	}

	/**
	 * Create a new page
	 *
	 * @param string $title Page title
	 * @param string $content Editor.js JSON content
	 * @param int $authorId Author user ID
	 * @param string $status Page status (draft, published)
	 * @param string|null $slug Optional custom slug (auto-generated if not provided)
	 * @param string $template Template name
	 * @param string|null $metaTitle SEO meta title
	 * @param string|null $metaDescription SEO meta description
	 * @param string|null $metaKeywords SEO meta keywords
	 * @return Page
	 */
	public function create(
		string $title,
		string $content,
		int $authorId,
		string $status,
		?string $slug = null,
		string $template = PageTemplate::DEFAULT->value,
		?string $metaTitle = null,
		?string $metaDescription = null,
		?string $metaKeywords = null
	): Page
	{
		$page = new Page();
		$page->setTitle( $title );
		$page->setSlug( $slug ?: $this->generateSlug( $title ) );
		$page->setContent( $content );
		$page->setTemplate( $template );
		$page->setMetaTitle( $metaTitle );
		$page->setMetaDescription( $metaDescription );
		$page->setMetaKeywords( $metaKeywords );
		$page->setAuthorId( $authorId );
		$page->setStatus( $status );
		$page->setCreatedAt( new DateTimeImmutable() );

		// Business rule: auto-set published date for published pages
		if( $status === ContentStatus::PUBLISHED->value )
		{
			$page->setPublishedAt( new DateTimeImmutable() );
		}

		return $this->_pageRepository->create( $page );
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
		$slug = strtolower( trim( $title ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		// Fallback for titles with no ASCII characters
		if( $slug === '' )
		{
			$slug = 'page-' . $this->_random->uniqueId();
		}

		return $slug;
	}
}
