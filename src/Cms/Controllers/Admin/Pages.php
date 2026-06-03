<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Page\IPageCreator;
use Neuron\Cms\Services\Page\IPageUpdater;
use Neuron\Cms\Services\Revision\IRevisionService;
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
	private ?IRevisionService $_revisions;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPageRepository $pageRepository
	 * @param IPageCreator $pageCreator
	 * @param IPageUpdater $pageUpdater
	 * @param IRevisionService|null $revisions
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPageRepository $pageRepository,
		IPageCreator $pageCreator,
		IPageUpdater $pageUpdater,
		?IRevisionService $revisions = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_pageRepository = $pageRepository;
		$this->_pageCreator = $pageCreator;
		$this->_pageUpdater = $pageUpdater;
		$this->_revisions = $revisions;
	}

	/**
	 * Resolve the revision service.
	 *
	 * PHP-DI autowiring does not inject optional constructor parameters, so the
	 * service is resolved lazily from the container when it was not supplied.
	 *
	 * @return IRevisionService|null
	 */
	private function getRevisionService(): ?IRevisionService
	{
		if( $this->_revisions instanceof IRevisionService )
		{
			return $this->_revisions;
		}

		try
		{
			$container = \Neuron\Patterns\Registry::getInstance()->get( \Neuron\Core\Registry\RegistryKeys::CONTAINER );

			if( $container instanceof \Neuron\Patterns\Container\IContainer && $container->has( IRevisionService::class ) )
			{
				$this->_revisions = $container->get( IRevisionService::class );
			}
		}
		catch( \Throwable $e )
		{
			// Best-effort: revision recording must never block content operations.
		}

		return $this->_revisions;
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
				FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey() => $sessionManager->getFlash( FlashMessageType::ERROR->value )
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

				$this->getRevisionService()?->recordPage( $page, \Neuron\Cms\Models\Revision::ACTION_CREATED );

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

			if( $success instanceof Page )
			{
				$this->getRevisionService()?->recordPage( $success, \Neuron\Cms\Models\Revision::ACTION_UPDATED );
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
	 * List revision history for a page
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/pages/:id/history', name: 'admin_pages_history')]
	public function history( Request $request ): string
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to view this page'] );
		}

		$this->initializeCsrfToken();

		$service = $this->getRevisionService();
		$revisions = $service ? $service->listForPage( $pageId ) : [];

		return $this->view()
			->title( 'Page History' )
			->description( 'Revision history' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'contentTitle' => $page->getTitle(),
				'contentId'    => $pageId,
				'revisions'    => $revisions,
				'routePrefix'  => 'admin_pages',
				'backRoute'    => 'admin_pages_edit'
			])
			->render( 'history', 'admin' );
	}

	/**
	 * View a single page revision
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/pages/:id/history/:revision', name: 'admin_pages_history_show')]
	public function historyShow( Request $request ): string
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$revisionId = (int)$request->getRouteParameter( 'revision' );

		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to view this page'] );
		}

		$revision = $this->getRevisionService()?->find( $revisionId );

		if( !$revision || $revision->getContentId() !== $pageId || $revision->getContentType() !== \Neuron\Cms\Models\Revision::TYPE_PAGE )
		{
			$this->redirect( 'admin_pages_history', ['id' => $pageId], [FlashMessageType::ERROR->value, 'Revision not found'] );
		}

		$this->initializeCsrfToken();

		$snapshot = $revision->getSnapshotData();
		$renderer = new \Neuron\Cms\Services\Content\EditorJsRenderer();
		$contentHtml = $renderer->render( json_decode( (string)( $snapshot['content'] ?? '{"blocks":[]}' ), true ) ?? ['blocks' => []] );

		return $this->view()
			->title( 'View Revision' )
			->description( 'Revision preview' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'contentTitle' => $page->getTitle(),
				'contentId'    => $pageId,
				'revision'     => $revision,
				'snapshot'     => $snapshot,
				'contentHtml'  => $contentHtml,
				'routePrefix'  => 'admin_pages',
				'backRoute'    => 'admin_pages_edit'
			])
			->render( 'history-show', 'admin' );
	}

	/**
	 * Restore a page to a previous revision
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Post('/pages/:id/history/:revision/restore', name: 'admin_pages_history_restore', filters: ['csrf'])]
	public function historyRestore( Request $request ): never
	{
		$pageId = (int)$request->getRouteParameter( 'id' );
		$revisionId = (int)$request->getRouteParameter( 'revision' );

		$page = $this->_pageRepository->findById( $pageId );

		if( !$page )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Page not found'] );
		}

		if( !is_admin() && !is_editor() && $page->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_pages', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this page'] );
		}

		$revision = $this->getRevisionService()?->find( $revisionId );

		if( !$revision || $revision->getContentId() !== $pageId || $revision->getContentType() !== \Neuron\Cms\Models\Revision::TYPE_PAGE )
		{
			$this->redirect( 'admin_pages_history', ['id' => $pageId], [FlashMessageType::ERROR->value, 'Revision not found'] );
		}

		try
		{
			$snapshot = $revision->getSnapshotData();

			$page->setTitle( $snapshot['title'] ?? $page->getTitle() );
			$page->setSlug( $snapshot['slug'] ?? $page->getSlug() );
			$page->setContent( (string)( $snapshot['content'] ?? $page->getContentRaw() ) );
			$page->setTemplate( $snapshot['template'] ?? $page->getTemplate() );
			$page->setMetaTitle( $snapshot['meta_title'] ?? null );
			$page->setMetaDescription( $snapshot['meta_description'] ?? null );
			$page->setMetaKeywords( $snapshot['meta_keywords'] ?? null );
			$page->setStatus( $snapshot['status'] ?? $page->getStatus() );

			$this->_pageRepository->update( $page );
			$this->getRevisionService()?->recordPage( $page, \Neuron\Cms\Models\Revision::ACTION_RESTORED );

			Log::info( "Page {$pageId} restored to revision {$revisionId} by user " . user_id() );
			$this->redirect( 'admin_pages_edit', ['id' => $pageId], [FlashMessageType::SUCCESS->value, 'Page restored from revision'] );
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to restore page {$pageId} to revision {$revisionId}: {$e->getMessage()}" );
			$this->redirect( 'admin_pages_history', ['id' => $pageId], [FlashMessageType::ERROR->value, 'Failed to restore revision: ' . $e->getMessage()] );
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
