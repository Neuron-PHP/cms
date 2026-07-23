<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Services\Event\IEventCreator;
use Neuron\Cms\Services\Event\IEventUpdater;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
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
	private IEventRegistrationRepository $_registrationRepository;
	private IEventCreator $_creator;
	private IEventUpdater $_updater;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IEventRepository $eventRepository
	 * @param IEventCategoryRepository $categoryRepository
	 * @param IEventRegistrationRepository $registrationRepository
	 * @param IEventCreator $creator
	 * @param IEventUpdater $updater
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		IEventRegistrationRepository $registrationRepository,
		IEventCreator $creator,
		IEventUpdater $updater
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_registrationRepository = $registrationRepository;
		$this->_creator = $creator;
		$this->_updater = $updater;
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

		// Registration counts keyed by event id (for the registrations column).
		$registrationCounts = [];
		foreach( $events as $event )
		{
			if( $event->isRegistrationEnabled() )
			{
				$registrationCounts[ $event->getId() ] = $this->_registrationRepository->countByEvent( $event->getId() );
			}
		}

		$sessionManager = $this->getSessionManager();
		return $this->view()
			->title( 'Events' )
			->description( 'Manage calendar events' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'events' => $events,
				'registrationCounts' => $registrationCounts,
				FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey() => $sessionManager->getFlash( FlashMessageType::ERROR->value )
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
	 * Duplicate an event and redirect to the edit form for the copy.
	 */
	#[Post('/events/:id/duplicate', name: 'admin_events_duplicate', filters: ['csrf'])]
	public function duplicate( Request $request ): never
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Unauthorized to duplicate this event'] );
		}

		try
		{
			$copy = $this->_creator->duplicate( $event, user_id() );
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $copy->getId() ],
				[ FlashMessageType::SUCCESS->value, 'Event duplicated. Update the details below, then publish when ready.' ]
			);
		}
		catch( \Exception $e )
		{
			$this->redirect(
				'admin_events',
				[],
				[ FlashMessageType::ERROR->value, 'Failed to duplicate event: ' . $e->getMessage() ]
			);
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

		// An optional occurrence query param lets the form scope edits to a
		// single occurrence of a recurring series.
		$occurrenceDate = trim( (string)( $request->get( 'occurrence', '' ) ?? '' ) );

		$seriesOccurrences = $event->isRecurring()
			? $this->_updater->listOccurrences( (int)$event->getId() )
			: [];

		return $this->view()
			->title( 'Edit Event' )
			->description( 'Edit calendar event' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'event' => $event,
				'categories' => $this->_categoryRepository->all(),
				'occurrence_date' => $occurrenceDate,
				'series_occurrences' => $seriesOccurrences
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
			$this->_eventRepository->delete( $event );
			$this->redirect( 'admin_events', [], [FlashMessageType::SUCCESS->value, 'Event deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Failed to delete event: ' . $e->getMessage()] );
		}
	}

	/**
	 * Cancel a single occurrence of a recurring series.
	 *
	 * Does not delete the series — the date is excluded via an exception so it
	 * no longer appears on the public calendar.
	 */
	#[Post('/events/:id/cancel-occurrence', name: 'admin_events_cancel_occurrence', filters: ['csrf'])]
	public function cancelOccurrence( Request $request ): never
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this event'] );
		}

		$occurrenceDate = trim( (string)( $request->post( 'occurrence_date', '' ) ?? '' ) );

		if( $occurrenceDate === '' )
		{
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::ERROR->value, 'Choose an occurrence date to cancel.' ]
			);
		}

		try
		{
			$this->_updater->cancelOccurrence( $eventId, $occurrenceDate );
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::SUCCESS->value, 'Occurrence cancelled. It will no longer appear on the calendar.' ]
			);
		}
		catch( \Exception $e )
		{
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::ERROR->value, 'Failed to cancel occurrence: ' . $e->getMessage() ]
			);
		}
	}

	/**
	 * Restore a previously cancelled occurrence of a recurring series.
	 */
	#[Post('/events/:id/restore-occurrence', name: 'admin_events_restore_occurrence', filters: ['csrf'])]
	public function restoreOccurrence( Request $request ): never
	{
		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Event not found'] );
		}

		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this event'] );
		}

		$occurrenceDate = trim( (string)( $request->post( 'occurrence_date', '' ) ?? '' ) );

		if( $occurrenceDate === '' )
		{
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::ERROR->value, 'Choose an occurrence date to restore.' ]
			);
		}

		try
		{
			$this->_updater->restoreOccurrence( $eventId, $occurrenceDate );
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::SUCCESS->value, 'Occurrence restored to the calendar.' ]
			);
		}
		catch( \Exception $e )
		{
			$this->redirect(
				'admin_events_edit',
				[ 'id' => $eventId ],
				[ FlashMessageType::ERROR->value, 'Failed to restore occurrence: ' . $e->getMessage() ]
			);
		}
	}
}
