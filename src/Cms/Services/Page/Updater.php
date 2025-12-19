<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use DateTimeImmutable;

/**
 * Page update service.
 *
 * Updates existing pages with validation.
 *
 * @package Neuron\Cms\Services\Page
 */
class Updater
{
	private IPageRepository $_pageRepository;

	public function __construct( IPageRepository $pageRepository )
	{
		$this->_pageRepository = $pageRepository;
	}

	/**
	 * Update an existing page
	 *
	 * @param Page $page Page to update
	 * @param string $title New title
	 * @param string $content New Editor.js JSON content
	 * @param string $status New status
	 * @param string|null $slug New slug (optional)
	 * @param string $template Template name
	 * @param string|null $metaTitle SEO meta title
	 * @param string|null $metaDescription SEO meta description
	 * @param string|null $metaKeywords SEO meta keywords
	 * @return bool True if updated successfully
	 */
	public function update(
		Page $page,
		string $title,
		string $content,
		string $status,
		?string $slug = null,
		string $template = Page::TEMPLATE_DEFAULT,
		?string $metaTitle = null,
		?string $metaDescription = null,
		?string $metaKeywords = null
	): bool
	{
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
		if( $status === Page::STATUS_PUBLISHED && !$page->getPublishedAt() )
		{
			$page->setPublishedAt( new DateTimeImmutable() );
		}

		$page->setUpdatedAt( new DateTimeImmutable() );

		return $this->_pageRepository->update( $page );
	}
}
