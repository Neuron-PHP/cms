<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Services\Event\Creator;
use Neuron\Cms\Services\Event\Updater;
use Neuron\Cms\Services\Event\Deleter;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use DateTimeImmutable;

/**
 * Admin event management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Events extends Content
{
	private DatabaseEventRepository $_eventRepository;
	private DatabaseEventCategoryRepository $_categoryRepository;
	private Creator $_creator;
	private Updater $_updater;
	private Deleter $_deleter;

	/**
	 * @param Application|null $app
	 * @param DatabaseEventRepository|null $eventRepository
	 * @param DatabaseEventCategoryRepository|null $categoryRepository
	 * @param Creator|null $creator
	 * @param Updater|null $updater
	 * @param Deleter|null $deleter
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabaseEventRepository $eventRepository = null,
		?DatabaseEventCategoryRepository $categoryRepository = null,
		?Creator $creator = null,
		?Updater $updater = null,
		?Deleter $deleter = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $eventRepository === null )
		{
			$settings = Registry::getInstance()->get( 'Settings' );

			$eventRepository = new DatabaseEventRepository( $settings );
			$categoryRepository = new DatabaseEventCategoryRepository( $settings );
			$creator = new Creator( $eventRepository, $categoryRepository );
			$updater = new Updater( $eventRepository, $categoryRepository );
			$deleter = new Deleter( $eventRepository );
		}

		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_creator = $creator;
		$this->_updater = $updater;
		$this->_deleter = $deleter;
	}

	/**
	 * List all events
	 */
	public function index( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		// Get all events or filter by creator if not admin/editor
		if( is_admin() || is_editor() )
		{
			$events = $this->_eventRepository->all();
		}
		else
		{
			$events = $this->_eventRepository->getByCreator( user_id() );
		}

		return $this->view()
			->title( 'Events' )
			->description( 'Manage calendar events' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'events' => $events,
				'Success' => $sessionManager->getFlash( 'success' ),
				'Error' => $sessionManager->getFlash( 'error' )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create event form
	 */
	public function create( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

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
	public function store( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event creation by user " . user_id() );
			$this->redirect( 'admin_events_create', [], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$title = $request->post( 'title', '' );
			$slug = $request->post( 'slug', '' );
			$description = $request->post( 'description', '' );
			$content = $request->post( 'content', '{"blocks":[]}' );
			$location = $request->post( 'location', '' );
			$startDate = $request->post( 'start_date', '' );
			$endDate = $request->post( 'end_date', '' );
			$allDay = (bool)$request->post( 'all_day', false );
			$categoryId = $request->post( 'category_id', '' );
			$status = $request->post( 'status', 'draft' );
			$featuredImage = $request->post( 'featured_image', '' );
			$organizer = $request->post( 'organizer', '' );
			$contactEmail = $request->post( 'contact_email', '' );
			$contactPhone = $request->post( 'contact_phone', '' );

			$this->_creator->create(
				$title,
				new DateTimeImmutable( $startDate ),
				user_id(),
				$status,
				$slug ?: null,
				$description ?: null,
				$content,
				$location ?: null,
				$endDate ? new DateTimeImmutable( $endDate ) : null,
				$allDay,
				$categoryId ? (int)$categoryId : null,
				$featuredImage ?: null,
				$organizer ?: null,
				$contactEmail ?: null,
				$contactPhone ?: null
			);

			$this->redirect( 'admin_events', [], ['success', 'Event created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events_create', [], ['error', 'Failed to create event: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit event form
	 */
	public function edit( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], ['error', 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

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
	public function update( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], ['error', 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event update: Event {$eventId}, user " . user_id() );
			$this->redirect( 'admin_events_edit', ['id' => $eventId], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$title = $request->post( 'title', '' );
			$slug = $request->post( 'slug', '' );
			$description = $request->post( 'description', '' );
			$content = $request->post( 'content', '{"blocks":[]}' );
			$location = $request->post( 'location', '' );
			$startDate = $request->post( 'start_date', '' );
			$endDate = $request->post( 'end_date', '' );
			$allDay = (bool)$request->post( 'all_day', false );
			$categoryId = $request->post( 'category_id', '' );
			$status = $request->post( 'status', 'draft' );
			$featuredImage = $request->post( 'featured_image', '' );
			$organizer = $request->post( 'organizer', '' );
			$contactEmail = $request->post( 'contact_email', '' );
			$contactPhone = $request->post( 'contact_phone', '' );

			$this->_updater->update(
				$event,
				$title,
				new DateTimeImmutable( $startDate ),
				$status,
				$slug ?: null,
				$description ?: null,
				$content,
				$location ?: null,
				$endDate ? new DateTimeImmutable( $endDate ) : null,
				$allDay,
				$categoryId ? (int)$categoryId : null,
				$featuredImage ?: null,
				$organizer ?: null,
				$contactEmail ?: null,
				$contactPhone ?: null
			);

			$this->redirect( 'admin_events', [], ['success', 'Event updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events_edit', ['id' => $eventId], ['error', 'Failed to update event: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete event
	 */
	public function destroy( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$eventId = (int)$request->getRouteParameter( 'id' );
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			$this->redirect( 'admin_events', [], ['error', 'Event not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $event->getCreatedBy() !== user_id() )
		{
			$this->redirect( 'admin_events', [], ['error', 'Unauthorized to delete this event'] );
		}

		// Validate CSRF token before any state changes
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event deletion: Event {$eventId}, user " . user_id() );
			$this->redirect( 'admin_events', [], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$this->_deleter->delete( $event );
			$this->redirect( 'admin_events', [], ['success', 'Event deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events', [], ['error', 'Failed to delete event: ' . $e->getMessage()] );
		}
	}
}
