<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;
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
class Creator implements IPageCreator
{
	private IPageRepository $_pageRepository;
	private SlugGenerator $_slugGenerator;

	public function __construct( IPageRepository $pageRepository, ?SlugGenerator $slugGenerator = null )
	{
		$this->_pageRepository = $pageRepository;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
	}

	/**
	 * Create a new page from DTO
	 *
	 * @param Dto $request DTO containing title, content, author_id, status, slug, template, meta_title, meta_description, meta_keywords
	 * @return Page
	 */
	public function create( Dto $request ): Page
	{
		// Extract values from DTO
		$title = $request->title;
		$content = $request->content;
		$authorId = $request->author_id;
		$status = $request->status;
		$slug = $request->slug ?? null;
		$template = $request->template ?? PageTemplate::DEFAULT->value;
		$metaTitle = $request->meta_title ?? null;
		$metaDescription = $request->meta_description ?? null;
		$metaKeywords = $request->meta_keywords ?? null;

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
		return $this->_slugGenerator->generate( $title, 'page' );
	}
}
