<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\EventCategory\IEventCategoryCreator;
use Neuron\Cms\Services\EventCategory\IEventCategoryUpdater;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin event category management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class EventCategories extends Content
{
	private IEventCategoryRepository $_repository;
	private IEventCategoryCreator $_creator;
	private IEventCategoryUpdater $_updater;

	/**
	 * @param Application|null $app
	 * @param IEventCategoryRepository|null $repository
	 * @param IEventCategoryCreator|null $creator
	 * @param IEventCategoryUpdater|null $updater
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IEventCategoryRepository $repository = null,
		?IEventCategoryCreator $creator = null,
		?IEventCategoryUpdater $updater = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_repository = $repository ?? $app?->getContainer()?->get( IEventCategoryRepository::class );
		$this->_creator = $creator ?? $app?->getContainer()?->get( IEventCategoryCreator::class );
		$this->_updater = $updater ?? $app?->getContainer()?->get( IEventCategoryUpdater::class );
	}

	/**
	 * List all event categories
	 */
	#[Get('/event-categories', name: 'admin_event_categories')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$sessionManager = $this->getSessionManager();
		return $this->view()
			->title( 'Event Categories' )
			->description( 'Manage event categories' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'categories' => $this->_repository->all(),
				FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create category form
	 */
	#[Get('/event-categories/create', name: 'admin_event_categories_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		$sessionManager = $this->getSessionManager();
		return $this->view()
			->title( 'Create Event Category' )
			->description( 'Create a new event category' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'errors' => $sessionManager->getFlash( 'errors' ) ?: [],
				'old' => $sessionManager->getFlash( 'old' ) ?: []
			])
			->render( 'create', 'admin' );
	}

	/**
	 * Store new category
	 */
	#[Post('/event-categories', name: 'admin_event_categories_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'event-categories/create-event-category-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_event_categories_create', $dto->getErrors() );
		}

		try
		{
			$this->_creator->create( $dto );
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::SUCCESS->value, 'Event category created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_event_categories_create', [], [FlashMessageType::ERROR->value, 'Failed to create category: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit category form
	 */
	#[Get('/event-categories/:id/edit', name: 'admin_event_categories_edit')]
	public function edit( Request $request ): string
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_repository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::ERROR->value, 'Category not found'] );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Event Category' )
			->description( 'Edit event category' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'category', $category )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update category
	 */
	#[Put('/event-categories/:id', name: 'admin_event_categories_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'event-categories/update-event-category-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $categoryId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_event_categories_edit', $dto->getErrors(), ['id' => $categoryId] );
		}

		try
		{
			$this->_updater->update( $dto );
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::SUCCESS->value, 'Event category updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_event_categories_edit', ['id' => $categoryId], [FlashMessageType::ERROR->value, 'Failed to update category: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete category
	 */
	#[Delete('/event-categories/:id', name: 'admin_event_categories_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_repository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::ERROR->value, 'Category not found'] );
		}

		try
		{
			$this->_repository->delete( $categoryId );
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::SUCCESS->value, 'Event category deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_event_categories', [], [FlashMessageType::ERROR->value, 'Failed to delete category: ' . $e->getMessage()] );
		}
	}
}
