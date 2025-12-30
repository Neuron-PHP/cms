<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Dto;
use DateTimeImmutable;

/**
 * Event update service.
 *
 * @package Neuron\Cms\Services\Event
 */
class Updater implements IEventUpdater
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
	 * Update an event from DTO
	 *
	 * @param Dto $request DTO containing id and event data
	 * @return Event
	 * @throws \RuntimeException if event not found, slug already exists, or category not found
	 */
	public function update( Dto $request ): Event
	{
		// Extract values from DTO
		$id = $request->id;
		$title = $request->title;
		$slug = $request->slug ?? '';
		$description = $request->description ?? null;
		$contentRaw = $request->content ?? '{"blocks":[]}';
		$location = $request->location ?? null;
		$startDate = new DateTimeImmutable( $request->start_date );
		$endDate = $request->end_date ? new DateTimeImmutable( $request->end_date ) : null;
		$allDay = $request->all_day ?? false;
		$categoryId = $request->category_id ?? null;
		$status = $request->status;
		$featuredImage = $request->featured_image ?? null;
		$organizer = $request->organizer ?? null;
		$contactEmail = $request->contact_email ?? null;
		$contactPhone = $request->contact_phone ?? null;

		// Look up the event
		$event = $this->_eventRepository->findById( $id );
		if( !$event )
		{
			throw new \RuntimeException( "Event with ID {$id} not found" );
		}

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
