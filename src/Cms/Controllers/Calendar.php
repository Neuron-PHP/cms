<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Services\Event\RecurrenceExpander;
use Neuron\Cms\Services\Event\RecurrenceRule;
use Neuron\Cms\Services\Widget\EventRegistrationWidget;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use DateTimeImmutable;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Public calendar controller.
 *
 * Displays calendar events to public users.
 *
 * @package Neuron\Cms\Controllers
 */
#[RouteGroup(prefix: '/calendar')]
class Calendar extends Content
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;
	private ?IEventRegistrationRepository $_registrationRepository;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IEventRepository $eventRepository
	 * @param IEventCategoryRepository $categoryRepository
	 * @param IEventRegistrationRepository|null $registrationRepository
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		?IEventRegistrationRepository $registrationRepository = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_registrationRepository = $registrationRepository;
	}

	/**
	 * Calendar index - show events in calendar/list view
	 */
	#[Get('/', name: 'calendar')]
	public function index( Request $request ): string
	{
		// Get month/year from query params (default to current month)
		$monthParam = $request->get( 'month', date( 'n' ) );
		$yearParam = $request->get( 'year', date( 'Y' ) );

		$month = (int)$monthParam;
		$year = (int)$yearParam;

		// Calculate start and end dates for the month
		$startDate = new DateTimeImmutable( "$year-$month-01 00:00:00" );
		$endDate = $startDate->modify( 'last day of this month' )->setTime( 23, 59, 59 );

		// Get events for this month
		$events = $this->_eventRepository->getByDateRange( $startDate, $endDate, 'published' );

		// Get all categories for filter
		$categories = $this->_categoryRepository->all();

		$viewData = [
			'Title' => 'Calendar | ' . $this->getName(),
			'Description' => 'View upcoming events',
			'events' => $events,
			'categories' => $categories,
			'currentMonth' => $month,
			'currentYear' => $year,
			'startDate' => $startDate,
			'endDate' => $endDate
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'default'
		);
	}

	/**
	 * Show single event detail
	 */
	#[Get('/event/:slug', name: 'calendar_event')]
	public function show( Request $request ): string
	{
		$slug = $request->getRouteParameter( 'slug' );
		$event = $this->_eventRepository->findBySlug( $slug );

		if( !$event || !$event->isPublished() )
		{
			throw new \RuntimeException( 'Event not found', 404 );
		}

		// Resolve a specific occurrence of a recurring series when requested.
		$occurrence = $this->resolveOccurrence( $event, $request->get( 'occurrence' ) );

		if( $occurrence !== null )
		{
			$event = $occurrence;
		}

		// Increment view count
		$this->_eventRepository->incrementViewCount( $event );

		// Render the registration form when registration is enabled for this event.
		$registrationForm = '';
		if( $event->isRegistrationEnabled() )
		{
			$widget = new EventRegistrationWidget(
				$this->_eventRepository,
				$this->_categoryRepository,
				$this->_registrationRepository,
				$this->getSessionManager()
			);

			$widgetAttrs = [ 'event' => $event->getSlug() ];

			if( $event->isOccurrence() )
			{
				$widgetAttrs['event'] = $slug;
				$widgetAttrs['occurrence'] = $event->getOccurrenceDate()->format( 'Y-m-d H:i:s' );
			}

			$registrationForm = $widget->render( $widgetAttrs );
		}

		$viewData = [
			'Title' => $event->getTitle() . ' | ' . $this->getName(),
			'Description' => $event->getDescription() ?? $event->getTitle(),
			'event' => $event,
			'registrationForm' => $registrationForm
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'show',
			'default'
		);
	}

	/**
	 * Resolve a specific occurrence of a recurring event for the detail page.
	 *
	 * Returns null when the event does not repeat, when no occurrence was
	 * requested (show the series landing), or when the requested occurrence is
	 * invalid or cancelled. A stored override row is preferred; otherwise the
	 * occurrence is synthesised from the master.
	 *
	 * @param Event $event
	 * @param mixed $occurrenceParam
	 * @return Event|null
	 */
	private function resolveOccurrence( Event $event, mixed $occurrenceParam ): ?Event
	{
		if( !$event->isRecurring() )
		{
			return null;
		}

		$raw = trim( (string)( $occurrenceParam ?? '' ) );

		if( $raw === '' )
		{
			return null;
		}

		try
		{
			$occurrence = new DateTimeImmutable( $raw );
		}
		catch( \Throwable $e )
		{
			return null;
		}

		// Prefer a stored override row (a modified single occurrence).
		$override = $this->_eventRepository->findOverride( $event->getId(), $occurrence );
		if( $override !== null && $override->isPublished() )
		{
			return $override;
		}

		if( !RecurrenceRule::occursAt( (string)$event->getRrule(), $event->getStartDate(), $occurrence ) )
		{
			return null;
		}

		// Skip cancelled occurrences.
		$occurrenceKey = $occurrence->format( 'Y-m-d H:i:s' );
		foreach( $this->_eventRepository->getExceptions( $event->getId() ) as $exception )
		{
			if( $exception->format( 'Y-m-d H:i:s' ) === $occurrenceKey )
			{
				return null;
			}
		}

		$expander = new RecurrenceExpander();

		return $expander->buildOccurrence( $event, $occurrence, $expander->duration( $event ) );
	}

	/**
	 * Show events filtered by category
	 */
	#[Get('/category/:slug', name: 'calendar_category')]
	public function category( Request $request ): string
	{
		$slug = $request->getRouteParameter( 'slug' );
		$category = $this->_categoryRepository->findBySlug( $slug );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found', 404 );
		}

		// Get upcoming events in this category
		$events = $this->_eventRepository->getByCategory( $category->getId(), 'published' );

		$viewData = [
			'Title' => $category->getName() . ' Events | ' . $this->getName(),
			'Description' => 'Events in ' . $category->getName(),
			'category' => $category,
			'events' => $events
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'category',
			'default'
		);
	}
}
