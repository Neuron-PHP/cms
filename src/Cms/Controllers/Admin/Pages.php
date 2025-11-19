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
use Neuron\Log\Log;

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

		// Validate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for page creation by user {$user->getId()}" );
			$this->redirect( 'admin_pages_create', [], ['error', 'Invalid security token. Please try again.'] );
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
			$page = $this->_pageCreator->create(
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

			if( !$page )
			{
				Log::error( "Page creation failed for user {$user->getId()}, title: {$title}" );
				$this->redirect( 'admin_pages_create', [], ['error', 'Failed to create page. Please try again.'] );
			}

			Log::info( "Page created successfully: ID {$page->getId()}, title: {$title}, by user {$user->getId()}" );
			$this->redirect( 'admin_pages', [], ['success', 'Page created successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page creation by user {$user->getId()}: {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages_create', [], ['error', 'Failed to create page. Please try again.'] );
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
			Log::warning( "Unauthorized edit attempt by user {$user->getId()} on page {$pageId}" );
			$this->redirect( 'admin_pages', [], ['error', 'Unauthorized to edit this page'] );
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
			Log::warning( "Unauthorized page update attempt: User {$user->getId()} tried to edit page {$pageId}" );
			$this->redirect( 'admin_pages', [], ['error', 'Unauthorized to edit this page'] );
		}

		// Validate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for page update: Page {$pageId}, user {$user->getId()}" );
			$this->redirect( 'admin_pages_edit', ['id' => $pageId], ['error', 'Invalid security token. Please try again.'] );
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
			$success = $this->_pageUpdater->update(
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

			if( !$success )
			{
				Log::error( "Page update failed: Page {$pageId}, user {$user->getId()}, title: {$title}" );
				$this->redirect( 'admin_pages_edit', ['id' => $pageId], ['error', 'Failed to update page. Please try again.'] );
			}

			Log::info( "Page updated successfully: Page {$pageId}, title: {$title}, by user {$user->getId()}" );
			$this->redirect( 'admin_pages', [], ['success', 'Page updated successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page update: Page {$pageId}, user {$user->getId()}: {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages_edit', ['id' => $pageId], ['error', 'Failed to update page. Please try again.'] );
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
			Log::warning( "Unauthorized page deletion attempt: User {$user->getId()} tried to delete page {$pageId}" );
			$this->redirect( 'admin_pages', [], ['error', 'Unauthorized to delete this page'] );
		}

		// Validate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for page deletion: Page {$pageId}, user {$user->getId()}" );
			$this->redirect( 'admin_pages', [], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$pageTitle = $page->getTitle(); // Store for logging before deletion

			$success = $this->_pageDeleter->delete( $page );

			if( !$success )
			{
				Log::error( "Page deletion failed: Page {$pageId}, user {$user->getId()}" );
				$this->redirect( 'admin_pages', [], ['error', 'Failed to delete page. Please try again.'] );
			}

			Log::info( "Page deleted successfully: Page {$pageId}, title: {$pageTitle}, by user {$user->getId()}" );
			$this->redirect( 'admin_pages', [], ['success', 'Page deleted successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page deletion: Page {$pageId}, user {$user->getId()}: {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages', [], ['error', 'Failed to delete page. Please try again.'] );
		}
	}
}
