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
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		$settings = Registry::getInstance()->get( 'Settings' );

		$this->_eventRepository = new DatabaseEventRepository( $settings );
		$this->_categoryRepository = new DatabaseEventCategoryRepository( $settings );
		$this->_creator = new Creator( $this->_eventRepository, $this->_categoryRepository );
		$this->_updater = new Updater( $this->_eventRepository, $this->_categoryRepository );
		$this->_deleter = new Deleter( $this->_eventRepository );
	}

	/**
	 * List all events
	 */
	public function index( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		// Get all events or filter by creator if not admin/editor
		if( $user->isAdmin() || $user->isEditor() )
		{
			$events = $this->_eventRepository->all();
		}
		else
		{
			$events = $this->_eventRepository->getByCreator( $user->getId() );
		}

		$viewData = [
			'Title' => 'Events | ' . $this->getName(),
			'Description' => 'Manage calendar events',
			'User' => $user,
			'events' => $events,
			'Success' => $sessionManager->getFlash( 'success' ),
			'Error' => $sessionManager->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'admin'
		);
	}

	/**
	 * Show create event form
	 */
	public function create( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Create Event | ' . $this->getName(),
			'Description' => 'Create a new calendar event',
			'User' => $user,
			'categories' => $this->_categoryRepository->all()
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'create',
			'admin'
		);
	}

	/**
	 * Store new event
	 */
	public function store( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event creation by user {$user->getId()}" );
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
				$user->getId(),
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
		$user = Registry::getInstance()->get( 'Auth.User' );

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
		if( !$user->isAdmin() && !$user->isEditor() && $event->getCreatedBy() !== $user->getId() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Edit Event | ' . $this->getName(),
			'Description' => 'Edit calendar event',
			'User' => $user,
			'event' => $event,
			'categories' => $this->_categoryRepository->all()
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'admin'
		);
	}

	/**
	 * Update event
	 */
	public function update( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

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
		if( !$user->isAdmin() && !$user->isEditor() && $event->getCreatedBy() !== $user->getId() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this event' );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event update: Event {$eventId}, user {$user->getId()}" );
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
		$user = Registry::getInstance()->get( 'Auth.User' );

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
		if( !$user->isAdmin() && !$user->isEditor() && $event->getCreatedBy() !== $user->getId() )
		{
			$this->redirect( 'admin_events', [], ['error', 'Unauthorized to delete this event'] );
		}

		// Validate CSRF token before any state changes
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event deletion: Event {$eventId}, user {$user->getId()}" );
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
