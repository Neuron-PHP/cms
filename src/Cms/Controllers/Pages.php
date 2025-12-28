<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Models\Page as PageModel;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Public pages controller.
 *
 * Displays published pages to site visitors.
 *
 * @package Neuron\Cms\Controllers
 */
class Pages extends Content
{
	private IPageRepository $_pageRepository;
	private EditorJsRenderer $_renderer;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get settings for repositories
		$settings = Registry::getInstance()->get( 'Settings' );

		// Initialize repository
		$this->_pageRepository = new DatabasePageRepository( $settings );

		// Initialize renderer with shortcode support
		$postRepository = new DatabasePostRepository( $settings );
		$widgetRenderer = new WidgetRenderer( $postRepository );
		$shortcodeParser = new ShortcodeParser( $widgetRenderer );
		$this->_renderer = new EditorJsRenderer( $shortcodeParser );
	}

	/**
	 * Display a page by slug
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	public function show( Request $request ): string
	{
		$slug = $request->getRouteParameter( 'slug', '' );
		$page = $this->_pageRepository->findBySlug( $slug );

		if( !$page || !$page->isPublished() )
		{
			throw new NotFound( 'Page not found' );
		}

		// Increment view count
		$this->_pageRepository->incrementViewCount( $page->getId() );

		// Render content from Editor.js JSON
		$content = $page->getContent();
		$contentHtml = $this->_renderer->render( $content );

		// Use meta title if available, otherwise use page title
		$metaTitle = $page->getMetaTitle() ?: $page->getTitle();
		$pageTitle = $metaTitle . ' | ' . $this->getName();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Page' => $page,
				'ContentHtml' => $contentHtml,
				'Title' => $pageTitle,
				'Description' => $page->getMetaDescription() ?: $this->getDescription(),
				'MetaKeywords' => $page->getMetaKeywords()
			],
			'show'
		);
	}
}
