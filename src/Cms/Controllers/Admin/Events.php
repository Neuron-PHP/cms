<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
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
	public function store( Request $request ): never
	{
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
			$this->_deleter->delete( $event );
			$this->redirect( 'admin_events', [], [FlashMessageType::SUCCESS->value, 'Event deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_events', [], [FlashMessageType::ERROR->value, 'Failed to delete event: ' . $e->getMessage()] );
		}
	}
}
