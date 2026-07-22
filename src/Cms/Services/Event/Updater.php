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
	private RecurrenceEditor $_recurrenceEditor;

	public function __construct(
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		?RecurrenceEditor $recurrenceEditor = null
	)
	{
		$this->_eventRepository = $eventRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_recurrenceEditor = $recurrenceEditor
			?? new RecurrenceEditor( $eventRepository, $categoryRepository );
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
		$featured = $request->featured ?? false;
		$registrationEnabled = $request->registration_enabled ?? false;
		$registrationVisibility = $request->registration_visibility ?? Event::VISIBILITY_PUBLIC;
		$capacity = $request->capacity ?? null;
		$featuredImage = $request->featured_image ?? null;
		$externalUrl = $request->external_url ?? null;
		$organizer = $request->organizer ?? null;
		$contactEmail = $request->contact_email ?? null;
		$contactPhone = $request->contact_phone ?? null;

		// Look up the event
		$event = $this->_eventRepository->findById( $id );
		if( !$event )
		{
			throw new \RuntimeException( "Event with ID {$id} not found" );
		}

		// Recurring events support scoped edits: a single occurrence (override
		// row) or "this and following" (series split). The default scope edits
		// the whole series (the master) in place.
		$scope = $request->recurrence_edit_scope ?? 'all';

		if( $event->isRecurring() && $scope === 'single' )
		{
			return $this->_recurrenceEditor->editSingle( $event, $request );
		}

		if( $event->isRecurring() && $scope === 'this_and_following' )
		{
			return $this->_recurrenceEditor->splitFromOccurrence( $event, $request );
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
		$this->applyRecurrence( $event, $request, $startDate );
		$event->setCategoryId( $categoryId );
		$event->setStatus( $status );
		$event->setFeatured( $featured );
		$event->setRegistrationEnabled( $registrationEnabled );
		$event->setRegistrationVisibility( $registrationVisibility );
		$event->setCapacity( $capacity !== null ? (int)$capacity : null );
		$event->setFeaturedImage( $featuredImage );
		$event->setExternalUrl( $externalUrl );
		$event->setOrganizer( $organizer );
		$event->setContactEmail( $contactEmail );
		$event->setContactPhone( $contactPhone );

		return $this->_eventRepository->update( $event );
	}

	/**
	 * Cancel a single occurrence of a recurring series.
	 *
	 * Accepts a full datetime or a date-only string (Y-m-d). Date-only values
	 * are combined with the master's start time so the exclusion matches the
	 * RRULE occurrence key.
	 *
	 * @param int $eventId
	 * @param string $occurrenceDate
	 * @return void
	 * @throws \RuntimeException
	 */
	public function cancelOccurrence( int $eventId, string $occurrenceDate ): void
	{
		$event = $this->_eventRepository->findById( $eventId );

		if( !$event )
		{
			throw new \RuntimeException( "Event with ID {$eventId} not found" );
		}

		$occurrence = $this->resolveOccurrenceDate( $event, $occurrenceDate );

		$this->_recurrenceEditor->cancelOccurrence( $event, $occurrence );
	}

	/**
	 * Parse an occurrence date, combining date-only values with the master time.
	 *
	 * @param Event $event
	 * @param string $raw
	 * @return DateTimeImmutable
	 * @throws \RuntimeException
	 */
	private function resolveOccurrenceDate( Event $event, string $raw ): DateTimeImmutable
	{
		$value = trim( $raw );

		if( $value === '' )
		{
			throw new \RuntimeException( 'An occurrence date is required to cancel an occurrence' );
		}

		if( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) === 1 )
		{
			$value .= ' ' . $event->getStartDate()->format( 'H:i:s' );
		}

		try
		{
			return new DateTimeImmutable( $value );
		}
		catch( \Throwable $e )
		{
			throw new \RuntimeException( 'Invalid occurrence date' );
		}
	}

	/**
	 * Compile recurrence fields from the DTO and apply them to the master.
	 *
	 * A raw `rrule` (when valid) takes precedence over the structured fields.
	 *
	 * @param Event $event
	 * @param Dto $request
	 * @param DateTimeImmutable $startDate
	 * @return void
	 * @throws \RuntimeException when an explicit rule is invalid
	 */
	private function applyRecurrence( Event $event, Dto $request, DateTimeImmutable $startDate ): void
	{
		$raw = trim( (string)( $request->rrule ?? '' ) );

		if( $raw !== '' )
		{
			if( !RecurrenceRule::isValid( $raw, $startDate ) )
			{
				throw new \RuntimeException( 'Invalid recurrence rule' );
			}

			$rrule = $raw;
		}
		else
		{
			$rrule = RecurrenceRule::compile( [
				'freq'     => $request->repeat_freq ?? 'none',
				'interval' => $request->repeat_interval ?? 1,
				'byday'    => $request->repeat_byday ?? '',
				'end'      => $request->repeat_end ?? 'never',
				'until'    => $request->repeat_until ?? null,
				'count'    => $request->repeat_count ?? null
			] );
		}

		$event->setRrule( $rrule );
		$event->setRecurrenceUntil(
			$rrule !== null ? RecurrenceRule::computeUntil( $rrule, $startDate ) : null
		);
	}
}
