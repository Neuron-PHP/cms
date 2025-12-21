<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Patterns\Registry;

/**
 * Calendar widget for displaying events via shortcode.
 *
 * Usage: [calendar category="slug" limit="5" upcoming="true"]
 *
 * @package Neuron\Cms\Services\Widget
 */
class CalendarWidget implements IWidget
{
	private DatabaseEventRepository $_eventRepository;
	private DatabaseEventCategoryRepository $_categoryRepository;

	public function __construct()
	{
		$settings = Registry::getInstance()->get( 'Settings' );
		$this->_eventRepository = new DatabaseEventRepository( $settings );
		$this->_categoryRepository = new DatabaseEventCategoryRepository( $settings );
	}

	/**
	 * Get widget shortcode name
	 */
	public function getName(): string
	{
		return 'calendar';
	}

	/**
	 * Render the calendar widget
	 *
	 * @param array $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string
	{
		$limit = $attrs['limit'] ?? 5;
		$upcoming = $attrs['upcoming'] ?? true;
		$categorySlug = $attrs['category'] ?? null;

		// Get events based on parameters
		if( $categorySlug )
		{
			$category = $this->_categoryRepository->findBySlug( $categorySlug );
			if( !$category )
			{
				return "<!-- Calendar widget: category '{$categorySlug}' not found -->";
			}
			$events = $this->_eventRepository->getByCategory( $category->getId(), 'published' );

			// Limit results if needed
			if( $limit )
			{
				$events = array_slice( $events, 0, (int)$limit );
			}
		}
		elseif( $upcoming )
		{
			$events = $this->_eventRepository->getUpcoming( (int)$limit, 'published' );
		}
		else
		{
			$events = $this->_eventRepository->getPast( (int)$limit, 'published' );
		}

		// Render events using simple template
		return $this->renderTemplate( $events, $attrs );
	}

	/**
	 * Get widget description
	 */
	public function getDescription(): string
	{
		return 'Display a list of calendar events';
	}

	/**
	 * Get supported attributes
	 */
	public function getAttributes(): array
	{
		return [
			'category' => 'Filter events by category slug (optional)',
			'limit' => 'Maximum number of events to display (default: 5)',
			'upcoming' => 'Show upcoming events (true) or past events (false) (default: true)'
		];
	}

	/**
	 * Render widget template
	 *
	 * @param array $events
	 * @param array $attrs
	 * @return string
	 */
	private function renderTemplate( array $events, array $attrs ): string
	{
		if( empty( $events ) )
		{
			return '<div class="calendar-widget"><p>No events found.</p></div>';
		}

		$html = '<div class="calendar-widget">';
		$html .= '<ul class="calendar-widget-list">';

		foreach( $events as $event )
		{
			$title = htmlspecialchars( $event->getTitle(), ENT_QUOTES, 'UTF-8' );
			$slug = htmlspecialchars( $event->getSlug(), ENT_QUOTES, 'UTF-8' );
			$startDate = $event->getStartDate()->format( 'F j, Y' );

			if( !$event->isAllDay() )
			{
				$startDate .= ' at ' . $event->getStartDate()->format( 'g:i A' );
			}

			$location = $event->getLocation();
			$locationHtml = $location ? '<span class="event-location">' . htmlspecialchars( $location, ENT_QUOTES, 'UTF-8' ) . '</span>' : '';

			$html .= '<li class="calendar-widget-item">';
			$html .= '<a href="/calendar/event/' . $slug . '" class="event-link">';
			$html .= '<span class="event-title">' . $title . '</span>';
			$html .= '<span class="event-date">' . $startDate . '</span>';
			$html .= $locationHtml;
			$html .= '</a>';
			$html .= '</li>';
		}

		$html .= '</ul>';
		$html .= '</div>';

		return $html;
	}
}
