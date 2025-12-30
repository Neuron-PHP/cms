<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Dto\Dto;
use DateTimeImmutable;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Enums\PageTemplate;

/**
 * Page update service.
 *
 * Updates existing pages with validation.
 *
 * @package Neuron\Cms\Services\Page
 */
class Updater implements IPageUpdater
{
	private IPageRepository $_pageRepository;

	public function __construct( IPageRepository $pageRepository )
	{
		$this->_pageRepository = $pageRepository;
	}

	/**
	 * Update an existing page from DTO
	 *
	 * @param Dto $request DTO containing id, title, content, status, slug, template, meta_title, meta_description, meta_keywords
	 * @return Page Updated page
	 * @throws \Exception If page not found
	 */
	public function update( Dto $request ): Page
	{
		// Extract values from DTO
		$id = $request->id;
		$title = $request->title;
		$content = $request->content;
		$status = $request->status;
		$slug = $request->slug ?? null;
		$template = $request->template ?? PageTemplate::DEFAULT->value;
		$metaTitle = $request->meta_title ?? null;
		$metaDescription = $request->meta_description ?? null;
		$metaKeywords = $request->meta_keywords ?? null;

		// Look up the page
		$page = $this->_pageRepository->findById( $id );
		if( !$page )
		{
			throw new \Exception( "Page with ID {$id} not found" );
		}

		$page->setTitle( $title );
		$page->setContent( $content );
		$page->setStatus( $status );
		$page->setTemplate( $template );
		$page->setMetaTitle( $metaTitle );
		$page->setMetaDescription( $metaDescription );
		$page->setMetaKeywords( $metaKeywords );

		if( $slug )
		{
			$page->setSlug( $slug );
		}

		// Business rule: set published date when status changes to published
		if( $status === ContentStatus::PUBLISHED->value && !$page->getPublishedAt() )
		{
			$page->setPublishedAt( new DateTimeImmutable() );
		}

		$page->setUpdatedAt( new DateTimeImmutable() );

		$this->_pageRepository->update( $page );

		return $page;
	}
}
