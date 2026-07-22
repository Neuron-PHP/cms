<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;
use DateTimeImmutable;

/**
 * Applies edits to recurring events according to an edit scope.
 *
 * Three scopes are supported (selected via the DTO's recurrence_edit_scope):
 *   - all                edit the master series in place (handled by Updater)
 *   - single             create/update an override row for one occurrence
 *   - this_and_following  split the series: bound the master before the
 *                         occurrence and create a new master for the remainder
 *
 * Occurrences can also be cancelled (excluded via EXDATE-style exceptions)
 * without deleting the series.
 *
 * @package Neuron\Cms\Services\Event
 */
class RecurrenceEditor
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
	 * Edit a single occurrence by creating or updating an override row.
	 *
	 * @param Event $master The recurring master
	 * @param Dto $request DTO carrying the edited fields + occurrence_date
	 * @return Event The override row
	 * @throws \RuntimeException when occurrence_date is missing/invalid
	 */
	public function editSingle( Event $master, Dto $request ): Event
	{
		$occurrence = $this->requireOccurrence( $request );

		$override = $this->_eventRepository->findOverride( $master->getId(), $occurrence );
		$isNew = $override === null;

		if( $isNew )
		{
			$override = new Event();
			$override->setRecurrenceParentId( $master->getId() );
			$override->setRecurrenceId( $occurrence );
			$override->setSlug( $this->occurrenceSlug( $master, $occurrence ) );
			$override->setCreatedBy( $master->getCreatedBy() ?? 0 );
		}

		$this->applyCommonFields( $override, $request );

		// Override rows never carry a rule of their own.
		$override->setRrule( null );
		$override->setRecurrenceParentId( $master->getId() );
		$override->setRecurrenceId( $occurrence );
		$override->setRecurrenceUntil( null );

		return $isNew
			? $this->_eventRepository->create( $override )
			: $this->_eventRepository->update( $override );
	}

	/**
	 * Split the series at the occurrence: bound the master before it and create
	 * a new master (carrying the edits) for this occurrence and all following.
	 *
	 * @param Event $master The recurring master
	 * @param Dto $request DTO carrying the edited fields + occurrence_date
	 * @return Event The new master for the remainder of the series
	 * @throws \RuntimeException when occurrence_date is missing/invalid
	 */
	public function splitFromOccurrence( Event $master, Dto $request ): Event
	{
		$occurrence = $this->requireOccurrence( $request );

		$originalRule = (string)$master->getRrule();
		$rule = RecurrenceRule::create( $originalRule, $master->getStartDate() );

		// Occurrences strictly before the split point. Uses getOccurrencesBetween
		// (not getOccurrencesBefore, whose occursAt() call mutates a
		// DateTimeImmutable and fatals on PHP 8.5+).
		$before = array_values( array_filter(
			$rule->getOccurrencesBetween( $master->getStartDate(), $occurrence ),
			fn( $date ) => $date < $occurrence
		) );

		if( empty( $before ) )
		{
			// Nothing precedes the occurrence: the whole series moves forward.
			$this->applyCommonFields( $master, $request );
			$newRule = $this->resolveForwardRule( $request, $originalRule, $master->getStartDate() );
			$master->setRrule( $newRule );
			$master->setRecurrenceUntil(
				$newRule !== null ? RecurrenceRule::computeUntil( $newRule, $master->getStartDate() ) : null
			);

			return $this->_eventRepository->update( $master );
		}

		// Bound the existing master to end before the split occurrence.
		$lastBefore = DateTimeImmutable::createFromInterface( end( $before ) );
		$boundedRule = RecurrenceRule::withUntil( $originalRule, $lastBefore );
		$master->setRrule( $boundedRule );
		$master->setRecurrenceUntil( RecurrenceRule::computeUntil( $boundedRule, $master->getStartDate() ) );
		$this->_eventRepository->update( $master );

		// Create the new master for the remainder of the series.
		$remainder = new Event();
		$remainder->setCreatedBy( $master->getCreatedBy() ?? 0 );
		$this->applyCommonFields( $remainder, $request );
		$remainder->setSlug( $this->occurrenceSlug( $master, $occurrence ) );

		$newRule = $this->resolveForwardRule( $request, $originalRule, $remainder->getStartDate() );
		$remainder->setRrule( $newRule );
		$remainder->setRecurrenceUntil(
			$newRule !== null ? RecurrenceRule::computeUntil( $newRule, $remainder->getStartDate() ) : null
		);

		return $this->_eventRepository->create( $remainder );
	}

	/**
	 * Cancel a single occurrence of a recurring series.
	 *
	 * Writes an exception so the date no longer appears on the public calendar.
	 * If a single-occurrence override row exists for that date, it is deleted.
	 *
	 * @param Event $master The recurring master
	 * @param DateTimeImmutable $occurrence Original occurrence start to exclude
	 * @return void
	 * @throws \RuntimeException when the event is not a recurring master or the
	 *                           date is not an occurrence of the series
	 */
	public function cancelOccurrence( Event $master, DateTimeImmutable $occurrence ): void
	{
		if( !$master->isRecurring() || $master->isRecurrenceOverride() )
		{
			throw new \RuntimeException( 'Only a recurring series can cancel an individual occurrence' );
		}

		$masterId = $master->getId();

		if( $masterId === null )
		{
			throw new \RuntimeException( 'Event must be saved before cancelling an occurrence' );
		}

		if( !RecurrenceRule::occursAt( (string)$master->getRrule(), $master->getStartDate(), $occurrence ) )
		{
			throw new \RuntimeException( 'That date is not an occurrence of this series' );
		}

		$this->_eventRepository->addException( $masterId, $occurrence );

		$override = $this->_eventRepository->findOverride( $masterId, $occurrence );

		if( $override !== null )
		{
			$this->_eventRepository->delete( $override );
		}
	}

	/**
	 * Resolve the rule for the going-forward series. Prefers a fresh rule
	 * compiled from the DTO's repeat fields; otherwise reuses the original
	 * pattern with any end-condition stripped.
	 *
	 * @param Dto $request
	 * @param string $originalRule
	 * @param DateTimeImmutable $start
	 * @return string|null
	 */
	private function resolveForwardRule( Dto $request, string $originalRule, DateTimeImmutable $start ): ?string
	{
		$raw = trim( (string)( $request->rrule ?? '' ) );
		if( $raw !== '' && RecurrenceRule::isValid( $raw, $start ) )
		{
			return $raw;
		}

		$compiled = RecurrenceRule::compile( [
			'freq'     => $request->repeat_freq ?? 'none',
			'interval' => $request->repeat_interval ?? 1,
			'byday'    => $request->repeat_byday ?? '',
			'end'      => $request->repeat_end ?? 'never',
			'until'    => $request->repeat_until ?? null,
			'count'    => $request->repeat_count ?? null
		] );

		if( $compiled !== null )
		{
			return $compiled;
		}

		return $originalRule !== '' ? RecurrenceRule::stripBound( $originalRule ) : null;
	}

	/**
	 * Map the standard editable event fields from the DTO onto an event.
	 *
	 * Does not touch id, slug or recurrence linkage.
	 *
	 * @param Event $event
	 * @param Dto $request
	 * @return void
	 */
	private function applyCommonFields( Event $event, Dto $request ): void
	{
		$event->setTitle( $request->title );
		$event->setDescription( $request->description ?? null );
		$event->setContent( $request->content ?? '{"blocks":[]}' );
		$event->setLocation( $request->location ?? null );
		$event->setStartDate( new DateTimeImmutable( $request->start_date ) );
		$event->setEndDate( $request->end_date ? new DateTimeImmutable( $request->end_date ) : null );
		$event->setAllDay( $request->all_day ?? false );
		$event->setStatus( $request->status );
		$event->setFeatured( $request->featured ?? false );
		$event->setRegistrationEnabled( $request->registration_enabled ?? false );
		$event->setRegistrationVisibility( $request->registration_visibility ?? Event::VISIBILITY_PUBLIC );
		$event->setCapacity( $request->capacity !== null ? (int)$request->capacity : null );
		$event->setFeaturedImage( $request->featured_image ?? null );
		$event->setExternalUrl( $request->external_url ?? null );
		$event->setOrganizer( $request->organizer ?? null );
		$event->setContactEmail( $request->contact_email ?? null );
		$event->setContactPhone( $request->contact_phone ?? null );

		$categoryId = $request->category_id ?? null;
		if( $categoryId )
		{
			$category = $this->_categoryRepository->findById( $categoryId );
			if( !$category )
			{
				throw new \RuntimeException( 'Event category not found' );
			}
			$event->setCategoryId( $categoryId );
		}
		else
		{
			$event->setCategoryId( null );
		}
	}

	/**
	 * Parse and require the occurrence_date from the DTO.
	 *
	 * @param Dto $request
	 * @return DateTimeImmutable
	 * @throws \RuntimeException
	 */
	private function requireOccurrence( Dto $request ): DateTimeImmutable
	{
		$value = trim( (string)( $request->occurrence_date ?? '' ) );

		if( $value === '' )
		{
			throw new \RuntimeException( 'An occurrence date is required to edit a single occurrence' );
		}

		return new DateTimeImmutable( $value );
	}

	/**
	 * Build a unique slug for an occurrence-derived event row.
	 *
	 * @param Event $master
	 * @param DateTimeImmutable $occurrence
	 * @return string
	 */
	private function occurrenceSlug( Event $master, DateTimeImmutable $occurrence ): string
	{
		$base = $master->getSlug() . '-' . $occurrence->format( 'Ymd-His' );

		if( !$this->_eventRepository->slugExists( $base ) )
		{
			return $base;
		}

		return $this->_slugGenerator->generate( $base, 'event' );
	}
}
