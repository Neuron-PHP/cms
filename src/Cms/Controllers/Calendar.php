<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
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

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IEventRepository|null $eventRepository
	 * @param IEventCategoryRepository|null $categoryRepository
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IEventRepository $eventRepository = null,
		?IEventCategoryRepository $categoryRepository = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		if( $eventRepository === null )
		{
			throw new \InvalidArgumentException( 'IEventRepository must be injected' );
		}
		$this->_eventRepository = $eventRepository;

		if( $categoryRepository === null )
		{
			throw new \InvalidArgumentException( 'IEventCategoryRepository must be injected' );
		}
		$this->_categoryRepository = $categoryRepository;
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

		// Increment view count
		$this->_eventRepository->incrementViewCount( $event );

		$viewData = [
			'Title' => $event->getTitle() . ' | ' . $this->getName(),
			'Description' => $event->getDescription() ?? $event->getTitle(),
			'event' => $event
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'show',
			'default'
		);
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
