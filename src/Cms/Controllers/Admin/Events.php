<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\Event\IEventCreator;
use Neuron\Cms\Services\Event\IEventUpdater;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use DateTimeImmutable;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin event management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Events extends Content
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;
	private IEventCreator $_creator;
	private IEventUpdater $_updater;

	/**
	 * @param Application|null $app
	 * @param IEventRepository|null $eventRepository
	 * @param IEventCategoryRepository|null $categoryRepository
	 * @param IEventCreator|null $creator
	 * @param IEventUpdater|null $updater
	 */
	public function __construct(
		?Application $app = null,
		?IEventRepository $eventRepository = null,
		?IEventCategoryRepository $categoryRepository = null,
		?IEventCreator $creator = null,
		?IEventUpdater $updater = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_eventRepository = $eventRepository ?? $app?->getContainer()?->get( IEventRepository::class );
		$this->_categoryRepository = $categoryRepository ?? $app?->getContainer()?->get( IEventCategoryRepository::class );
		$this->_creator = $creator ?? $app?->getContainer()?->get( IEventCreator::class );
		$this->_updater = $updater ?? $app?->getContainer()?->get( IEventUpdater::class );
	}

	/**
	 * List all events
	 */
	#[Get('/events', name: 'admin_events')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		// Get all events or filter by creator if not admin/editor
		if( is_admin() || is_editor() )
		{
			$events = $this->_eventRepository->all();
		}
		else
		{
			$events = $this->_eventRepository->getByCreator( user_id() );
		}

		$sessionManager = $this->getSessionManager();
		return $this->view()
			->title( 'Events' )
			->description( 'Manage calendar events' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'events' => $events,
				FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create event form
	 */
	#[Get('/events/create', name: 'admin_events_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create Event' )
			->description( 'Create a new calendar event' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'categories', $this->_categoryRepository->all() )
			->render( 'create', 'admin' );
	}

	/**
	 * Store new event
	 */
	#[Post('/events', name: 'admin_events_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'events/create-event-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set created_by from current user
		$dto->created_by = user_id();

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_events_create', $dto->getErrors() );
		}

		try
		{
			$this->_creator->create( $dto );
			$this->redirect( 'admin_events', [], [FlashMessageType::SUCCESS->value, 'Event created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events_create', [], [FlashMessageType::ERROR->value, 'Failed to create event: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit event form
	 */
	#[Get('/events/:id/edit', name: 'admin_events_edit')]
	public function edit( Request $request ): string
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Event' )
			->description( 'Edit calendar event' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'event' => $event,
				'categories' => $this->_categoryRepository->all()
			])
			->render( 'edit', 'admin' );
	}

	/**
	 * Update event
	 */
	#[Put('/events/:id', name: 'admin_events_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'events/update-event-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $eventId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_events_edit', $dto->getErrors(), ['id' => $eventId] );
		}

		try
		{
			$this->_updater->update( $dto );
			$this->redirect( 'admin_events', [], [FlashMessageType::SUCCESS->value, 'Event updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events_edit', ['id' => $eventId], [FlashMessageType::ERROR->value, 'Failed to update event: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete event
	 */
	#[Delete('/events/:id', name: 'admin_events_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Unauthorized to delete this event'] );
		}

		try
		{
			$this->_eventRepository->delete( $eventId );
			$this->redirect( 'admin_events', [], [FlashMessageType::SUCCESS->value, 'Event deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Failed to delete event: ' . $e->getMessage()] );
		}
	}
}
