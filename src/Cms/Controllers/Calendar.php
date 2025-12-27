<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use DateTimeImmutable;

/**
 * Public calendar controller.
 *
 * Displays calendar events to public users.
 *
 * @package Neuron\Cms\Controllers
 */
class Calendar extends Content
{
	private DatabaseEventRepository $_eventRepository;
	private DatabaseEventCategoryRepository $_categoryRepository;

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
	}

	/**
	 * Calendar index - show events in calendar/list view
	 */
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
