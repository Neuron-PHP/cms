<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;
use DateTimeImmutable;

/**
 * Event creation service.
 *
 * @package Neuron\Cms\Services\Event
 */
class Creator implements IEventCreator
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;
	private SlugGenerator $_slugGenerator;

	public function __construct(
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		?SlugGenerator $slugGenerator = null
	)
	{
		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
	}

	/**
	 * Create a new event from DTO
	 *
	 * @param Dto $request DTO containing event data
	 * @return Event
	 * @throws \RuntimeException if slug already exists or category not found
	 */
	public function create( Dto $request ): Event
	{
		// Extract values from DTO
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
		$createdBy = $request->created_by;

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
		return $this->_slugGenerator->generate( $title, 'event' );
	}
}
