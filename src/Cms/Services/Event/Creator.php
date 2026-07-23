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
		$featured = $request->featured ?? false;
		$registrationEnabled = $request->registration_enabled ?? false;
		$registrationVisibility = $request->registration_visibility ?? Event::VISIBILITY_PUBLIC;
		$capacity = $request->capacity ?? null;
		$featuredImage = $request->featured_image ?? null;
		$externalUrl = $request->external_url ?? null;
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
		$this->applyRecurrence( $event, $request, $startDate );
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
	 * Duplicate an existing event as a new draft.
	 *
	 * Copies content and scheduling details, assigns a unique slug, clears
	 * view counts and recurrence-override linkage, and sets status to draft
	 * so the copy can be reviewed before publishing.
	 *
	 * @param Event $source
	 * @param int $createdBy
	 * @return Event
	 */
	public function duplicate( Event $source, int $createdBy ): Event
	{
		$copy = new Event();
		$copy->setTitle( $source->getTitle() . ' (Copy)' );
		$copy->setSlug(
			$this->_slugGenerator->generateUnique(
				$source->getSlug() . '-copy',
				fn( string $slug ) => $this->_eventRepository->slugExists( $slug ),
				'event'
			)
		);
		$copy->setDescription( $source->getDescription() );
		$copy->setContent( $source->getContentRaw() );
		$copy->setLocation( $source->getLocation() );
		$copy->setStartDate( $source->getStartDate() );
		$copy->setEndDate( $source->getEndDate() );
		$copy->setAllDay( $source->isAllDay() );
		$copy->setCategoryId( $source->getCategoryId() );
		$copy->setStatus( Event::STATUS_DRAFT );
		$copy->setFeatured( $source->isFeatured() );
		$copy->setRegistrationEnabled( $source->isRegistrationEnabled() );
		$copy->setRegistrationVisibility( $source->getRegistrationVisibility() );
		$copy->setCapacity( $source->getCapacity() );
		$copy->setFeaturedImage( $source->getFeaturedImage() );
		$copy->setExternalUrl( $source->getExternalUrl() );
		$copy->setOrganizer( $source->getOrganizer() );
		$copy->setContactEmail( $source->getContactEmail() );
		$copy->setContactPhone( $source->getContactPhone() );
		$copy->setCreatedBy( $createdBy );
		$copy->setViewCount( 0 );

		// Always create a standalone event — do not keep override linkage.
		$copy->setRecurrenceParentId( null );
		$copy->setRecurrenceId( null );

		if( $source->isRecurrenceOverride() )
		{
			$copy->setRrule( null );
			$copy->setRecurrenceUntil( null );
		}
		else
		{
			$copy->setRrule( $source->getRrule() );
			$copy->setRecurrenceUntil( $source->getRecurrenceUntil() );
		}

		return $this->_eventRepository->create( $copy );
	}

	/**
	 * Compile recurrence fields from the DTO and apply them to the event.
	 *
	 * A raw `rrule` (when valid) takes precedence over the structured fields.
	 * The cached recurrence_until is computed for bounded rules.
	 *
	 * @param Event $event
	 * @param Dto $request
	 * @param DateTimeImmutable $startDate Series start (DTSTART)
	 * @return void
	 */
	private function applyRecurrence( Event $event, Dto $request, DateTimeImmutable $startDate ): void
	{
		$rrule = $this->resolveRrule( $request, $startDate );

		$event->setRrule( $rrule );
		$event->setRecurrenceUntil(
			$rrule !== null ? RecurrenceRule::computeUntil( $rrule, $startDate ) : null
		);
	}

	/**
	 * Resolve a recurrence rule string from the DTO.
	 *
	 * @param Dto $request
	 * @param DateTimeImmutable $startDate
	 * @return string|null
	 * @throws \RuntimeException when an explicit rule is invalid
	 */
	private function resolveRrule( Dto $request, DateTimeImmutable $startDate ): ?string
	{
		$raw = trim( (string)( $request->rrule ?? '' ) );

		if( $raw !== '' )
		{
			if( !RecurrenceRule::isValid( $raw, $startDate ) )
			{
				throw new \RuntimeException( 'Invalid recurrence rule' );
			}

			return $raw;
		}

		return RecurrenceRule::compile( [
			'freq'           => $request->repeat_freq ?? 'none',
			'interval'       => $request->repeat_interval ?? 1,
			'byday'          => $request->repeat_byday ?? '',
			'monthly_mode'   => $request->repeat_monthly_mode ?? 'day',
			'month_ordinal'  => $request->repeat_month_ordinal ?? null,
			'month_weekday'  => $request->repeat_month_weekday ?? null,
			'end'            => $request->repeat_end ?? 'never',
			'until'          => $request->repeat_until ?? null,
			'count'          => $request->repeat_count ?? null
		] );
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
