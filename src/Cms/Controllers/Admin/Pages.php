<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Services\Page\Creator;
use Neuron\Cms\Services\Page\Updater;
use Neuron\Cms\Services\Page\Deleter;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin page management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Pages extends Content
{
	private DatabasePageRepository $_pageRepository;
	private Creator $_pageCreator;
	private Updater $_pageUpdater;
	private Deleter $_pageDeleter;

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

		// Initialize services
		$this->_pageCreator = new Creator( $this->_pageRepository );
		$this->_pageUpdater = new Updater( $this->_pageRepository );
		$this->_pageDeleter = new Deleter( $this->_pageRepository );
	}

	/**
	 * List all pages
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		// Get all pages or filter by author if not admin
		if( $user->isAdmin() || $user->isEditor() )
		{
			$pages = $this->_pageRepository->all();
		}
		else
		{
			$pages = $this->_pageRepository->getByAuthor( $user->getId() );
		}

		$viewData = [
			'Title' => 'Pages | ' . $this->getName(),
			'Description' => 'Manage pages',
			'User' => $user,
			'pages' => $pages,
			'Success' => $sessionManager->getFlash( 'success' ),
			'Error' => $sessionManager->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'pages/index',
			'admin'
		);
	}

	/**
	 * Show create page form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Create Page | ' . $this->getName(),
			'Description' => 'Create a new page',
			'User' => $user
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'pages/create',
			'admin'
		);
	}

	/**
	 * Store new page
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function store( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get form data
			$title = $request->post( 'title', '' );
			$slug = $request->post( 'slug', '' );
			$content = $request->post( 'content', '{"blocks":[]}' );
			$template = $request->post( 'template', Page::TEMPLATE_DEFAULT );
			$metaTitle = $request->post( 'meta_title', '' );
			$metaDescription = $request->post( 'meta_description', '' );
			$metaKeywords = $request->post( 'meta_keywords', '' );
			$status = $request->post( 'status', Page::STATUS_DRAFT );

			// Create page using service
			$this->_pageCreator->create(
				$title,
				$content,
				$user->getId(),
				$status,
				$slug ?: null,
				$template,
				$metaTitle ?: null,
				$metaDescription ?: null,
				$metaKeywords ?: null
			);

			$this->redirect( 'admin_pages', [], ['success', 'Page created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_pages_create', [], ['error', 'Failed to create page: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit page form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], ['error', 'Page not found'] );
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $page->getAuthorId() !== $user->getId() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this page' );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Edit Page | ' . $this->getName(),
			'Description' => 'Edit page',
			'User' => $user,
			'page' => $page
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'pages/edit',
			'admin'
		);
	}

	/**
	 * Update page
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], ['error', 'Page not found'] );
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $page->getAuthorId() !== $user->getId() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this page' );
		}

		try
		{
			// Get form data
			$title = $request->post( 'title', '' );
			$slug = $request->post( 'slug', '' );
			$content = $request->post( 'content', '{"blocks":[]}' );
			$template = $request->post( 'template', Page::TEMPLATE_DEFAULT );
			$metaTitle = $request->post( 'meta_title', '' );
			$metaDescription = $request->post( 'meta_description', '' );
			$metaKeywords = $request->post( 'meta_keywords', '' );
			$status = $request->post( 'status', Page::STATUS_DRAFT );

			// Update page using service
			$this->_pageUpdater->update(
				$page,
				$title,
				$content,
				$status,
				$slug ?: null,
				$template,
				$metaTitle ?: null,
				$metaDescription ?: null,
				$metaKeywords ?: null
			);

			$this->redirect( 'admin_pages', [], ['success', 'Page updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_pages_edit', ['id' => $pageId], ['error', 'Failed to update page: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete page
	 * @param Request $request
	 * @return never
	 */
	public function destroy( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], ['error', 'Page not found'] );
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $page->getAuthorId() !== $user->getId() )
		{
			$this->redirect( 'admin_pages', [], ['error', 'Unauthorized to delete this page'] );
		}

		try
		{
			$this->_pageDeleter->delete( $page );
			$this->redirect( 'admin_pages', [], ['success', 'Page deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_pages', [], ['error', 'Failed to delete page: ' . $e->getMessage()] );
		}
	}
}
