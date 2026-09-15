<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Enums\HomepageMode;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;

/**
 * Configurable public homepage.
 *
 * neuron.yaml homepage.mode:
 *   blog    — paginated published posts (default)
 *   page    — a designated CMS page (homepage.page slug)
 *   landing — the stock marketing landing view
 *
 * @package Neuron\Cms\Controllers
 */
class Home extends Content
{
	private IPostRepository $_posts;
	private IPageRepository $_pages;
	private ICategoryRepository $_categories;
	private ITagRepository $_tags;
	private EditorJsRenderer $_renderer;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPostRepository $posts,
		IPageRepository $pages,
		ICategoryRepository $categories,
		ITagRepository $tags,
		EditorJsRenderer $renderer
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_posts = $posts;
		$this->_pages = $pages;
		$this->_categories = $categories;
		$this->_tags = $tags;
		$this->_renderer = $renderer;
	}

	#[Get('/', name: 'home')]
	public function index( Request $request ): string
	{
		$mode = HomepageMode::fromValue( $this->_settings->get( 'homepage', 'mode' ) );

		return match( $mode )
		{
			HomepageMode::PAGE => $this->renderConfiguredPage(),
			HomepageMode::LANDING => $this->renderLanding(),
			default => $this->renderBlog( $request ),
		};
	}

	private function renderLanding(): string
	{
		$title = $this->getName() . ' | ' . $this->getTitle();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			array_merge(
				$this->seoViewData( $title, $this->getDescription(), $this->absoluteRoute( 'home' ) ),
				[
					'Name' => $this->getName(),
					'Description' => $this->getDescription(),
					'RegistrationEnabled' => (bool) ( $this->_settings->get( 'member', 'registration_enabled' ) ?? false ),
				]
			),
			'index'
		);
	}

	private function renderConfiguredPage(): string
	{
		$slug = trim( (string) ( $this->_settings->get( 'homepage', 'page' ) ?? '' ) );
		$page = $slug !== '' ? $this->_pages->findBySlug( $slug ) : null;

		if( !$page || !$page->isPublished() )
		{
			return $this->renderLanding();
		}

		$metaTitle = $page->getMetaTitle() ?: $page->getTitle();
		$title = $metaTitle . ' | ' . $this->getName();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			array_merge(
				$this->seoViewData(
					$title,
					$page->getMetaDescription() ?: $this->getDescription(),
					$this->absoluteRoute( 'home' ),
					null,
					'website',
					$page->getMetaKeywords()
				),
				[
					'Page' => $page,
					'ContentHtml' => $this->_renderer->render( $page->getContent() ),
					'Template' => $page->getTemplate(),
					'ShowDates' => $this->_settings->get( 'pages', 'show_dates' ) ?? true,
					'ShowAuthor' => $this->_settings->get( 'pages', 'show_author' ) ?? true,
					'ShowViewCount' => $this->_settings->get( 'pages', 'show_view_count' ) ?? true,
				]
			),
			'page'
		);
	}

	private function renderBlog( Request $request ): string
	{
		$perPage = max( 1, (int) ( $this->_settings->get( 'blog', 'posts_per_page' ) ?? 10 ) );
		$page    = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );
		$total   = $this->_posts->count( ContentStatus::PUBLISHED->value );
		$pages   = max( 1, (int) ceil( $total / $perPage ) );
		$page    = min( $page, $pages );
		$offset  = ( $page - 1 ) * $perPage;

		$title = $this->getName() . ' | ' . $this->getTitle();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			array_merge(
				$this->seoViewData( $title, $this->getDescription(), $this->absoluteRoute( 'home' ) ),
				[
					'Posts' => $this->_posts->getPublished( $perPage, $offset ),
					'Categories' => $this->_categories->all(),
					'Tags' => $this->_tags->all(),
					'Name' => $this->getName(),
					'page' => $page,
					'pages' => $pages,
					'perPage' => $perPage,
					'total' => $total,
					'PaginationBase' => route_path( 'home' ) ?: '/',
				]
			),
			'blog'
		);
	}
}
