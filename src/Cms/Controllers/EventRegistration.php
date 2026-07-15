<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventRegistration as EventRegistrationModel;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\EventRegistration\RegistrationService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Public event registration controller.
 *
 * Handles submissions from the [event-registration] shortcode. Registrations
 * are persisted before notifications are sent so nothing is lost on delivery
 * failure. Private events require a logged-in member.
 *
 * @package Neuron\Cms\Controllers
 */
class EventRegistration extends Content
{
	private IEventRepository $_eventRepository;
	private IEventRegistrationRepository $_repository;
	private RegistrationService $_registrationService;

	/**
	 * Honeypot field name; matches EventRegistrationWidget.
	 */
	private const HONEYPOT_FIELD = 'form_extra_field';

	/**
	 * Flash keys consumed by EventRegistrationWidget.
	 */
	private const FLASH_SUCCESS = 'event_registration.success';
	private const FLASH_ERROR   = 'event_registration.error';

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IEventRepository $eventRepository
	 * @param IEventRegistrationRepository $repository
	 * @param RegistrationService|null $registrationService
	 */
	public function __construct(
		IMvcApplication              $app,
		SettingManager               $settings,
		SessionManager               $sessionManager,
		IEventRepository             $eventRepository,
		IEventRegistrationRepository $repository,
		?RegistrationService         $registrationService = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_eventRepository     = $eventRepository;
		$this->_repository          = $repository;
		$this->_registrationService = $registrationService ?? new RegistrationService( $settings );
	}

	/**
	 * Issue a fresh CSRF token for registration forms (used by the widget script).
	 * Kept separate so cached page markup can still obtain a valid token.
	 */
	#[Get('/events/register/token', name: 'event_registration_token')]
	public function token( Request $request ): string
	{
		$csrf  = new CsrfToken( $this->getSessionManager() );
		$token = $csrf->getToken();

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $token ] );
	}

	/**
	 * Handle an event registration submission.
	 *
	 * Persists first, then notifies. The CSRF filter runs before this method
	 * via the route filter.
	 */
	#[Post('/events/register', name: 'event_registration_submit', filters: ['csrf'])]
	public function submit( Request $request ): never
	{
		$eventId = (int)( $request->post( 'event_id', 0 ) ?? 0 );
		$event   = $eventId > 0 ? $this->_eventRepository->findById( $eventId ) : null;

		if( !$event || !$event->isPublished() || !$event->isRegistrationEnabled() )
		{
			Log::warning( "Event registration for unavailable event id: '{$eventId}'" );
			$this->redirectBack( '/calendar', [ self::FLASH_ERROR, 'Sorry, that event is not open for registration.' ] );
		}

		// Honeypot: bots fill the hidden field. Pretend success without storing.
		$honeypot = $request->post( self::HONEYPOT_FIELD, '' );
		if( !empty( $honeypot ) )
		{
			Log::info( "Event registration rejected by honeypot for event '{$eventId}'" );
			$this->redirectBack( '/calendar', [ self::FLASH_SUCCESS, $this->_registrationService->getSuccessMessage() ] );
		}

		// Private events require a logged-in member.
		$userId = $this->currentUserId();

		if( $event->isPrivate() && $userId === null )
		{
			$redirectTarget = $_SERVER['HTTP_REFERER'] ?? $this->eventUrl( $event );
			$this->redirectToUrl(
				'/login?redirect=' . urlencode( $redirectTarget ),
				[ self::FLASH_ERROR, 'Please log in to register for this event.' ]
			);
		}

		$name  = trim( (string)( $request->post( 'name', '' ) ?? '' ) );
		$email = trim( (string)( $request->post( 'email', '' ) ?? '' ) );
		$notes = trim( (string)( $request->post( 'notes', '' ) ?? '' ) );

		$errors = $this->validate( $name, $email );

		if( !empty( $errors ) )
		{
			$message = 'Please correct the following: ' . implode( ' ', $errors );
			$this->redirectBack( $this->eventUrl( $event ), [ self::FLASH_ERROR, $message ] );
		}

		// For recurring events the registration targets a specific occurrence so
		// capacity and duplicate-email checks are scoped per occurrence.
		$occurrence = $this->resolveOccurrence( $event, $request );

		if( $this->_repository->existsForEmail( $event->getId(), $email, $occurrence ) )
		{
			$this->redirectBack(
				$this->eventUrl( $event ),
				[ self::FLASH_ERROR, 'This email address is already registered for this event.' ]
			);
		}

		// Authoritative capacity check (the widget hides the form when full, but
		// re-check here in case the event filled up between render and submit).
		if( $event->hasCapacityLimit() && $event->isFull( $this->_repository->countByEvent( $event->getId(), $occurrence ) ) )
		{
			$this->redirectBack(
				$this->eventUrl( $event ),
				[ self::FLASH_ERROR, 'Sorry, this event is now full.' ]
			);
		}

		$registration = new EventRegistrationModel();
		$registration->setEventId( $event->getId() );
		$registration->setOccurrenceDate( $occurrence );
		$registration->setUserId( $userId );
		$registration->setName( $name );
		$registration->setEmail( $email );
		$registration->setNotes( $notes !== '' ? $notes : null );
		$registration->setStatus( EventRegistrationModel::STATUS_REGISTERED );
		$registration->setIpAddress( $request->getClientIp() );
		$registration->setUserAgent( substr( (string)$request->server( 'HTTP_USER_AGENT', '' ), 0, 500 ) );

		// Persist first so the registration survives even if email delivery fails.
		try
		{
			$registration = $this->_repository->create( $registration );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Event registration persistence failed: ' . $e->getMessage() );
			$this->redirectBack(
				$this->eventUrl( $event ),
				[ self::FLASH_ERROR, 'Sorry, we could not complete your registration. Please try again.' ]
			);
		}

		// Notifications are best-effort; the registration is already stored.
		$this->_registrationService->notifyAdmin( $registration, $event );
		$this->_registrationService->sendConfirmation( $registration, $event );

		$this->redirectBack( $this->eventUrl( $event ), [ self::FLASH_SUCCESS, $this->_registrationService->getSuccessMessage() ] );
	}

	/**
	 * Validate the submitted fields.
	 *
	 * @param string $name
	 * @param string $email
	 * @return array<int, string>
	 */
	private function validate( string $name, string $email ): array
	{
		$errors = [];

		if( $name === '' )
		{
			$errors[] = 'Name is required.';
		}

		if( $email === '' )
		{
			$errors[] = 'Email is required.';
		}
		elseif( !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$errors[] = 'A valid email address is required.';
		}

		return $errors;
	}

	/**
	 * Resolve the occurrence a registration targets.
	 *
	 * Returns null for non-recurring events. For recurring events the submitted
	 * occurrence_date is validated against the rule; an invalid value falls back
	 * to the series start.
	 *
	 * @param Event $event
	 * @param Request $request
	 * @return \DateTimeImmutable|null
	 */
	private function resolveOccurrence( Event $event, Request $request ): ?\DateTimeImmutable
	{
		if( !$event->isRecurring() )
		{
			return null;
		}

		$raw = trim( (string)( $request->post( 'occurrence_date', '' ) ?? '' ) );

		if( $raw === '' )
		{
			return $event->getStartDate();
		}

		try
		{
			$occurrence = new \DateTimeImmutable( $raw );
		}
		catch( \Throwable $e )
		{
			return $event->getStartDate();
		}

		if( \Neuron\Cms\Services\Event\RecurrenceRule::occursAt( (string)$event->getRrule(), $event->getStartDate(), $occurrence ) )
		{
			return $occurrence;
		}

		return $event->getStartDate();
	}

	/**
	 * Get the current logged-in user's id from the session (null when guest).
	 *
	 * @return int|null
	 */
	private function currentUserId(): ?int
	{
		$session = $this->getSessionManager();

		if( !$session->has( 'user_id' ) )
		{
			return null;
		}

		$userId = (int)$session->get( 'user_id' );

		return $userId > 0 ? $userId : null;
	}

	/**
	 * Build the public URL for an event.
	 *
	 * @param Event $event
	 * @return string
	 */
	private function eventUrl( Event $event ): string
	{
		return '/calendar/event/' . $event->getSlug();
	}
}
