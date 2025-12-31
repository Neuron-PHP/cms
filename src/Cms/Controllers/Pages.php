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
use Neuron\Mvc\Application;
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
	 * @param Application|null $app
	 * @param IPageRepository|null $pageRepository
	 * @param EditorJsRenderer|null $renderer
	 * @param SettingManager|null $settings
	 * @param SessionManager|null $sessionManager
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IPageRepository $pageRepository = null,
		?EditorJsRenderer $renderer = null,
		?SettingManager $settings = null,
		?SessionManager $sessionManager = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		// Pure dependency injection - no service locator fallback
		if( $pageRepository === null )
		{
			throw new \InvalidArgumentException( 'IPageRepository must be injected' );
		}

		if( $renderer === null )
		{
			throw new \InvalidArgumentException( 'EditorJsRenderer must be injected' );
		}

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
