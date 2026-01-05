<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\Page as PageModel;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Public pages controller.
 *
 * Displays published pages to site visitors.
 *
 * @package Neuron\Cms\Controllers
 */
#[RouteGroup(prefix: '/pages')]
class Pages extends Content
{
	private IPageRepository $_pageRepository;
	private EditorJsRenderer $_renderer;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPageRepository $pageRepository
	 * @param EditorJsRenderer $renderer
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPageRepository $pageRepository,
		EditorJsRenderer $renderer
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_pageRepository = $pageRepository;
		$this->_renderer = $renderer;
	}

	/**
	 * Display a page by slug
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/:slug', name: 'page')]
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
