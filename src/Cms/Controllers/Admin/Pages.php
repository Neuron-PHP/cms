<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Page\IPageCreator;
use Neuron\Cms\Services\Page\IPageUpdater;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Log\Log;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Enums\PageTemplate;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin page management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Pages extends Content
{
	private IPageRepository $_pageRepository;
	private IPageCreator $_pageCreator;
	private IPageUpdater $_pageUpdater;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPageRepository|null $pageRepository
	 * @param IPageCreator|null $pageCreator
	 * @param IPageUpdater|null $pageUpdater
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IPageRepository $pageRepository = null,
		?IPageCreator $pageCreator = null,
		?IPageUpdater $pageUpdater = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		if( $pageRepository === null )
		{
			throw new \InvalidArgumentException( 'IPageRepository must be injected' );
		}
		$this->_pageRepository = $pageRepository;

		if( $pageCreator === null )
		{
			throw new \InvalidArgumentException( 'IPageCreator must be injected' );
		}
		$this->_pageCreator = $pageCreator;

		if( $pageUpdater === null )
		{
			throw new \InvalidArgumentException( 'IPageUpdater must be injected' );
		}
		$this->_pageUpdater = $pageUpdater;
	}

	/**
	 * List all pages
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/pages', name: 'admin_pages')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		// Get all pages or filter by author if not admin
		$sessionManager = $this->getSessionManager();
		if( is_admin() || is_editor() )
		{
			$pages = $this->_pageRepository->all();
		}
		else
		{
			$pages = $this->_pageRepository->getByAuthor( user_id() );
		}

		return $this->view()
			->title( 'Pages' )
			->description( 'Manage pages' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'pages' => $pages,
				FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create page form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/pages/create', name: 'admin_pages_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create Page' )
			->description( 'Create a new page' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'create', 'admin' );
	}

	/**
	 * Store new page
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Post('/pages', name: 'admin_pages_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'pages/create-page-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set author from current user
		$dto->author_id = user_id();

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_pages_create', $dto->getErrors() );
		}

		try
		{
			// Pass DTO to service
			$page = $this->_pageCreator->create( $dto );

			if( !$page )
			{
				Log::error( "Page creation failed for user " . user_id() . ", title: {$dto->title}" );
				$this->redirect( 'admin_pages_create', [], [FlashMessageType::ERROR->value, 'Failed to create page. Please try again.'] );
			}

			Log::info( "Page created successfully: ID {$page->getId()}, title: {$dto->title}, by user " . user_id() );
			$this->redirect( 'admin_pages', [], [FlashMessageType::SUCCESS->value, 'Page created successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page creation by user " . user_id() . ": {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages_create', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Show edit page form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/pages/:id/edit', name: 'admin_pages_edit')]
	public function edit( Request $request ): string
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			Log::warning( "Unauthorized edit attempt by user " . user_id() . " on page {$pageId}" );
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this page'] );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Page' )
			->description( 'Edit page' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'page', $page )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update page
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Put('/pages/:id', name: 'admin_pages_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			Log::warning( "Unauthorized page update attempt: User " . user_id() . " tried to edit page {$pageId}" );
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this page'] );
		}

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'pages/update-page-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $pageId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_pages_edit', $dto->getErrors(), ['id' => $pageId] );
		}

		try
		{
			// Pass DTO to service
			$success = $this->_pageUpdater->update( $dto );

			if( !$success )
			{
				Log::error( "Page update failed: Page {$pageId}, user " . user_id() . ", title: {$dto->title}" );
				$this->redirect( 'admin_pages_edit', ['id' => $pageId], [FlashMessageType::ERROR->value, 'Failed to update page. Please try again.'] );
			}

			Log::info( "Page updated successfully: Page {$pageId}, title: {$dto->title}, by user " . user_id() );
			$this->redirect( 'admin_pages', [], [FlashMessageType::SUCCESS->value, 'Page updated successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page update: Page {$pageId}, user " . user_id() . ": {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages_edit', ['id' => $pageId], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Delete page
	 * @param Request $request
	 * @return never
	 */
	#[Delete('/pages/:id', name: 'admin_pages_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			Log::warning( "Unauthorized page deletion attempt: User " . user_id() . " tried to delete page {$pageId}" );
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to delete this page'] );
		}

		try
		{
			$pageTitle = $page->getTitle(); // Store for logging before deletion

			$success = $this->_pageRepository->delete( $pageId );

			if( !$success )
			{
				Log::error( "Page deletion failed: Page {$pageId}, user " . user_id() );
				$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Failed to delete page. Please try again.'] );
			}

			Log::info( "Page deleted successfully: Page {$pageId}, title: {$pageTitle}, by user " . user_id() );
			$this->redirect( 'admin_pages', [], [FlashMessageType::SUCCESS->value, 'Page deleted successfully'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Exception during page deletion: Page {$pageId}, user " . user_id() . ": {$e->getMessage()}", [
				'exception' => $e,
				'trace' => $e->getTraceAsString()
			] );
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Failed to delete page. Please try again.'] );
		}
	}
}
