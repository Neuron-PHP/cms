<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Core\System\IRandom;
use Neuron\Core\System\RealRandom;
use DateTimeImmutable;

/**
 * Event creation service.
 *
 * @package Neuron\Cms\Services\Event
 */
class Creator
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;
	private IRandom $_random;

	public function __construct(
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		?IRandom $random = null
	)
	{
		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_random = $random ?? new RealRandom();
	}

	/**
	 * Create a new event
	 *
	 * @param string $title Event title
	 * @param DateTimeImmutable $startDate Event start date/time
	 * @param int $createdBy User ID of creator
	 * @param string $status Event status (draft, published)
	 * @param string|null $slug Optional custom slug (auto-generated if not provided)
	 * @param string|null $description Optional short description
	 * @param string $contentRaw Editor.js JSON content (default empty)
	 * @param string|null $location Optional location
	 * @param DateTimeImmutable|null $endDate Optional end date/time
	 * @param bool $allDay Whether event is all-day
	 * @param int|null $categoryId Optional category ID
	 * @param string|null $featuredImage Optional featured image URL
	 * @param string|null $organizer Optional organizer name
	 * @param string|null $contactEmail Optional contact email
	 * @param string|null $contactPhone Optional contact phone
	 * @return Event
	 * @throws \RuntimeException if slug already exists or category not found
	 */
	public function create(
		string $title,
		DateTimeImmutable $startDate,
		int $createdBy,
		string $status,
		?string $slug = null,
		?string $description = null,
		string $contentRaw = '{"blocks":[]}',
		?string $location = null,
		?DateTimeImmutable $endDate = null,
		bool $allDay = false,
		?int $categoryId = null,
		?string $featuredImage = null,
		?string $organizer = null,
		?string $contactEmail = null,
		?string $contactPhone = null
	): Event
	{
		$event = new Event();
		$event->setTitle( $title );
		$event->setSlug( $slug ?: $this->generateSlug( $title ) );
		$event->setDescription( $description );
		$event->setContent( $contentRaw );
		$event->setLocation( $location );
		$event->setStartDate( $startDate );
		$event->setEndDate( $endDate );
		$event->setAllDay( $allDay );
		$event->setStatus( $status );
		$event->setFeaturedImage( $featuredImage );
		$event->setOrganizer( $organizer );
		$event->setContactEmail( $contactEmail );
		$event->setContactPhone( $contactPhone );
		$event->setCreatedBy( $createdBy );

		// Validate and set category
		if( $categoryId )
		{
			$category = $this->_categoryRepository->findById( $categoryId );
			if( !$category )
			{
				throw new \RuntimeException( 'Event category not found' );
			}
			$event->setCategoryId( $categoryId );
		}

		// Check for duplicate slug
		if( $this->_eventRepository->slugExists( $event->getSlug() ) )
		{
			throw new \RuntimeException( 'An event with this slug already exists' );
		}

		return $this->_eventRepository->create( $event );
	}

	/**
	 * Generate URL-friendly slug from title
	 *
	 * @param string $title
	 * @return string
	 */
	private function generateSlug( string $title ): string
	{
		$slug = strtolower( trim( $title ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		// Fallback for titles with no ASCII characters
		if( $slug === '' )
		{
			$slug = 'event-' . $this->_random->uniqueId();
		}

		return $slug;
	}
}
