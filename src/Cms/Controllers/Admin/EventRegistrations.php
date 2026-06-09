<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for browsing event registrations.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class EventRegistrations extends Content
{
	private const PER_PAGE = 25;

	private IEventRegistrationRepository $_repository;
	private IEventRepository $_eventRepository;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IEventRegistrationRepository $repository
	 * @param IEventRepository $eventRepository
	 */
	public function __construct(
		IMvcApplication              $app,
		SettingManager               $settings,
		SessionManager               $sessionManager,
		IEventRegistrationRepository $repository,
		IEventRepository             $eventRepository
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository      = $repository;
		$this->_eventRepository = $eventRepository;
	}

	/**
	 * List registrations (paginated, optional ?event= filter).
	 */
	#[Get('/event-registrations', name: 'admin_event_registrations')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$page    = max( 1, (int)( $request->get( 'page', 1 ) ?? 1 ) );
		$eventId = (int)( $request->get( 'event', 0 ) ?? 0 );
		$eventId = $eventId > 0 ? $eventId : null;

		$result = $this->_repository->paginate( $page, self::PER_PAGE, $eventId );

		// Map event ids -> titles for display, plus the full list for the filter.
		$events     = $this->_eventRepository->all();
		$eventTitles = [];
		foreach( $events as $event )
		{
			$eventTitles[ $event->getId() ] = $event->getTitle();
		}

		$activeEvent = $eventId !== null ? $this->_eventRepository->findById( $eventId ) : null;

		return $this->view()
			->title( 'Event Registrations | Admin' )
			->description( 'Review event registrations' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'registrations' => $result['items'],
				'total'         => $result['total'],
				'page'          => $result['page'],
				'pages'         => $result['pages'],
				'perPage'       => $result['per_page'],
				'events'        => $events,
				'eventTitles'   => $eventTitles,
				'activeEventId' => $eventId,
				'activeEvent'   => $activeEvent
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single registration.
	 */
	#[Get('/event-registrations/:id', name: 'admin_event_registration_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id           = (int)$request->getRouteParameter( 'id' );
		$registration = $this->_repository->findById( $id );

		if( $registration === null )
		{
			$this->redirect( 'admin_event_registrations', [], [ FlashMessageType::ERROR->value, 'Registration not found' ] );
		}

		$event = $this->_eventRepository->findById( $registration->getEventId() );

		return $this->view()
			->title( 'Event Registration | Admin' )
			->description( 'Event registration detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'registration' => $registration,
				'event'        => $event
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Delete a registration.
	 */
	#[Delete('/event-registrations/:id', name: 'admin_event_registration_delete', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int)$request->getRouteParameter( 'id' );

		try
		{
			$this->_repository->delete( $id );
			$this->redirect( 'admin_event_registrations', [], [ FlashMessageType::SUCCESS->value, 'Registration deleted' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_event_registrations', [], [ FlashMessageType::ERROR->value, 'Failed to delete registration: ' . $e->getMessage() ] );
		}
	}
}
