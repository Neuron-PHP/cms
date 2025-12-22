<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use DateTimeImmutable;

/**
 * Event update service.
 *
 * @package Neuron\Cms\Services\Event
 */
class Updater
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;

	public function __construct(
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository
	)
	{
		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
	}

	/**
	 * Update an event
	 *
	 * @param Event $event
	 * @param string $title
	 * @param DateTimeImmutable $startDate
	 * @param string $status
	 * @param string|null $slug
	 * @param string|null $description
	 * @param string $contentRaw
	 * @param string|null $location
	 * @param DateTimeImmutable|null $endDate
	 * @param bool $allDay
	 * @param int|null $categoryId
	 * @param string|null $featuredImage
	 * @param string|null $organizer
	 * @param string|null $contactEmail
	 * @param string|null $contactPhone
	 * @return Event
	 * @throws \RuntimeException if slug already exists for another event or category not found
	 */
	public function update(
		Event $event,
		string $title,
		DateTimeImmutable $startDate,
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
		// Check for duplicate slug (excluding current event)
		if( $slug && $this->_eventRepository->slugExists( $slug, $event->getId() ) )
		{
			throw new \RuntimeException( 'An event with this slug already exists' );
		}

		// Validate category if provided
		if( $categoryId )
		{
			$category = $this->_categoryRepository->findById( $categoryId );
			if( !$category )
			{
				throw new \RuntimeException( 'Event category not found' );
			}
		}

		$event->setTitle( $title );
		if( $slug )
		{
			$event->setSlug( $slug );
		}
		$event->setDescription( $description );
		$event->setContent( $contentRaw );
		$event->setLocation( $location );
		$event->setStartDate( $startDate );
		$event->setEndDate( $endDate );
		$event->setAllDay( $allDay );
		$event->setCategoryId( $categoryId );
		$event->setStatus( $status );
		$event->setFeaturedImage( $featuredImage );
		$event->setOrganizer( $organizer );
		$event->setContactEmail( $contactEmail );
		$event->setContactPhone( $contactPhone );

		return $this->_eventRepository->update( $event );
	}
}
