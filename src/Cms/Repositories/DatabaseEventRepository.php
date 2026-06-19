<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Event\RecurrenceExpander;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed event repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseEventRepository implements IEventRepository
{
	protected PDO $_pdo;
	private SettingManager $_settings;
	private RecurrenceExpander $_expander;

	/**
	 * Default look-ahead horizon (months) for expanding open-ended recurring
	 * series in list queries (upcoming/past/category).
	 */
	private const DEFAULT_HORIZON_MONTHS = 12;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
		$this->_settings = $settings;
		$this->_expander = new RecurrenceExpander();

		// Set PDO connection on Model class for ORM queries
		Event::setPdo( $this->_pdo );
	}

	/**
	 * Get all events
	 */
	public function all(): array
	{
		// Override rows (single-occurrence edits) are managed through their
		// master and are excluded from the top-level listing.
		$stmt = $this->_pdo->query(
			"SELECT * FROM events WHERE recurrence_parent_id IS NULL ORDER BY start_date DESC"
		);
		$rows = $stmt->fetchAll();

		return $this->hydrateRows( $rows );
	}

	/**
	 * Find event by ID
	 */
	public function findById( int $id ): ?Event
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM events WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		$event = Event::fromArray( $row );
		$this->loadRelations( $event );

		return $event;
	}

	/**
	 * Find event by slug
	 */
	public function findBySlug( string $slug ): ?Event
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM events WHERE slug = ?" );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		$event = Event::fromArray( $row );
		$this->loadRelations( $event );

		return $event;
	}

	/**
	 * Get events by category
	 */
	public function getByCategory( int $categoryId, string $status = 'published' ): array
	{
		// Non-recurring events (and single-occurrence overrides) in the category.
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE category_id = ? AND status = ? AND rrule IS NULL
			ORDER BY start_date ASC"
		);
		$stmt->execute( [ $categoryId, $status ] );
		$events = $this->hydrateRows( $stmt->fetchAll() );

		// Recurring masters in the category, expanded across the look-ahead window.
		$now = new DateTimeImmutable();
		$events = array_merge( $events, $this->expandInRange(
			$now,
			$this->horizonEnd( $now ),
			$status,
			' AND category_id = ?',
			[ $categoryId ]
		) );

		$this->sortByStartAsc( $events );

		return $events;
	}

	/**
	 * Get upcoming events
	 */
	public function getUpcoming( ?int $limit = null, string $status = 'published' ): array
	{
		$now = new DateTimeImmutable();

		// Non-recurring events (and overrides) starting now or later.
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE start_date >= ? AND status = ? AND rrule IS NULL
			ORDER BY start_date ASC"
		);
		$stmt->execute( [ $now->format( 'Y-m-d H:i:s' ), $status ] );
		$events = $this->hydrateRows( $stmt->fetchAll() );

		// Recurring occurrences within the look-ahead window.
		$events = array_merge( $events, $this->expandInRange( $now, $this->horizonEnd( $now ), $status ) );

		$this->sortByStartAsc( $events );

		return $limit ? array_slice( $events, 0, (int)$limit ) : $events;
	}

	/**
	 * Get upcoming events within a single category
	 */
	public function getUpcomingByCategory( int $categoryId, ?int $limit = 3, string $status = 'published' ): array
	{
		$now = new DateTimeImmutable();

		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE category_id = ? AND start_date >= ? AND status = ? AND rrule IS NULL
			ORDER BY start_date ASC"
		);
		$stmt->execute( [ $categoryId, $now->format( 'Y-m-d H:i:s' ), $status ] );
		$events = $this->hydrateRows( $stmt->fetchAll() );

		$events = array_merge( $events, $this->expandInRange(
			$now,
			$this->horizonEnd( $now ),
			$status,
			' AND category_id = ?',
			[ $categoryId ]
		) );

		$this->sortByStartAsc( $events );

		return $limit ? array_slice( $events, 0, (int)$limit ) : $events;
	}

	/**
	 * Get past events
	 */
	public function getPast( ?int $limit = null, string $status = 'published' ): array
	{
		$now = new DateTimeImmutable();
		$nowFormatted = $now->format( 'Y-m-d H:i:s' );

		// Non-recurring events (and overrides) that have already ended.
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE ((end_date IS NOT NULL AND end_date < ?)
				OR (end_date IS NULL AND start_date < ?))
			AND status = ? AND rrule IS NULL
			ORDER BY start_date DESC"
		);
		$stmt->execute( [ $nowFormatted, $nowFormatted, $status ] );
		$events = $this->hydrateRows( $stmt->fetchAll() );

		// Recurring occurrences within the look-back window that have ended.
		$horizonStart = $now->sub( new \DateInterval( 'P' . $this->horizonMonths() . 'M' ) );
		foreach( $this->expandInRange( $horizonStart, $now, $status ) as $occurrence )
		{
			if( $occurrence->isPast() )
			{
				$events[] = $occurrence;
			}
		}

		// Most recent first.
		usort( $events, fn( Event $a, Event $b ) => $b->getStartDate() <=> $a->getStartDate() );

		return $limit ? array_slice( $events, 0, (int)$limit ) : $events;
	}

	/**
	 * Get the next available featured event
	 */
	public function getNextFeatured( string $status = 'published' ): ?Event
	{
		$now = new DateTimeImmutable();
		$nowFormatted = $now->format( 'Y-m-d H:i:s' );

		// Soonest non-recurring featured event that has not yet ended.
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE featured = 1 AND status = ? AND rrule IS NULL
				AND (
					(end_date IS NOT NULL AND end_date >= ?)
					OR (end_date IS NULL AND start_date >= ?)
				)
			ORDER BY start_date ASC
			LIMIT 1"
		);
		$stmt->execute( [ $status, $nowFormatted, $nowFormatted ] );
		$row = $stmt->fetch();

		$candidate = null;

		if( $row )
		{
			$candidate = Event::fromArray( $row );
			$this->loadRelations( $candidate );
		}

		// Soonest upcoming featured recurring occurrence.
		$occurrences = $this->expandInRange(
			$now,
			$this->horizonEnd( $now ),
			$status,
			' AND featured = 1',
			[]
		);

		foreach( $occurrences as $occurrence )
		{
			if( $occurrence->isPast() )
			{
				continue;
			}

			if( $candidate === null || $occurrence->getStartDate() < $candidate->getStartDate() )
			{
				$candidate = $occurrence;
			}
		}

		return $candidate;
	}

	/**
	 * Get events by date range
	 */
	public function getByDateRange( DateTimeImmutable $startDate, DateTimeImmutable $endDate, string $status = 'published' ): array
	{
		$startFormatted = $startDate->format( 'Y-m-d H:i:s' );
		$endFormatted = $endDate->format( 'Y-m-d H:i:s' );

		// Non-recurring events (and single-occurrence overrides) overlapping the range.
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE rrule IS NULL
			AND (
				(start_date >= ? AND start_date <= ?)
				OR (end_date >= ? AND end_date <= ?)
				OR (start_date <= ? AND end_date >= ?)
			)
			AND status = ?
			ORDER BY start_date ASC"
		);

		$stmt->execute( [
			$startFormatted, $endFormatted,
			$startFormatted, $endFormatted,
			$startFormatted, $endFormatted,
			$status
		] );
		$events = $this->hydrateRows( $stmt->fetchAll() );

		// Recurring masters expanded into occurrences within the range.
		$events = array_merge( $events, $this->expandInRange( $startDate, $endDate, $status ) );

		$this->sortByStartAsc( $events );

		return $events;
	}

	/**
	 * Get events by creator
	 */
	public function getByCreator( int $userId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE created_by = ? AND recurrence_parent_id IS NULL
			ORDER BY start_date DESC"
		);
		$stmt->execute( [ $userId ] );

		return $this->hydrateRows( $stmt->fetchAll() );
	}

	/**
	 * Create new event
	 */
	public function create( Event $event ): Event
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO events (
				title, slug, description, content_raw, location, start_date, end_date,
				all_day, rrule, recurrence_parent_id, recurrence_id, recurrence_until,
				category_id, status, featured, registration_enabled, registration_visibility, capacity,
				featured_image, external_url, organizer, contact_email,
				contact_phone, created_by, view_count, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$now = new DateTimeImmutable();
		$event->setCreatedAt( $now );
		$event->setUpdatedAt( $now );

		$stmt->execute( [
			$event->getTitle(),
			$event->getSlug(),
			$event->getDescription(),
			$event->getContentRaw(),
			$event->getLocation(),
			$event->getStartDate()->format( 'Y-m-d H:i:s' ),
			$event->getEndDate()?->format( 'Y-m-d H:i:s' ),
			$event->isAllDay() ? 1 : 0,
			$event->getRrule(),
			$event->getRecurrenceParentId(),
			$event->getRecurrenceId()?->format( 'Y-m-d H:i:s' ),
			$event->getRecurrenceUntil()?->format( 'Y-m-d H:i:s' ),
			$event->getCategoryId(),
			$event->getStatus(),
			$event->isFeatured() ? 1 : 0,
			$event->isRegistrationEnabled() ? 1 : 0,
			$event->getRegistrationVisibility(),
			$event->getCapacity(),
			$event->getFeaturedImage(),
			$event->getExternalUrl(),
			$event->getOrganizer(),
			$event->getContactEmail(),
			$event->getContactPhone(),
			$event->getCreatedBy(),
			$event->getViewCount(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$event->setId( (int)$this->_pdo->lastInsertId() );

		return $event;
	}

	/**
	 * Update event
	 */
	public function update( Event $event ): Event
	{
		$stmt = $this->_pdo->prepare(
			"UPDATE events SET
				title = ?, slug = ?, description = ?, content_raw = ?, location = ?,
				start_date = ?, end_date = ?, all_day = ?,
				rrule = ?, recurrence_parent_id = ?, recurrence_id = ?, recurrence_until = ?,
				category_id = ?, status = ?,
				featured = ?, registration_enabled = ?, registration_visibility = ?, capacity = ?,
				featured_image = ?, external_url = ?, organizer = ?, contact_email = ?, contact_phone = ?,
				view_count = ?, updated_at = ?
			WHERE id = ?"
		);

		$now = new DateTimeImmutable();
		$event->setUpdatedAt( $now );

		$stmt->execute( [
			$event->getTitle(),
			$event->getSlug(),
			$event->getDescription(),
			$event->getContentRaw(),
			$event->getLocation(),
			$event->getStartDate()->format( 'Y-m-d H:i:s' ),
			$event->getEndDate()?->format( 'Y-m-d H:i:s' ),
			$event->isAllDay() ? 1 : 0,
			$event->getRrule(),
			$event->getRecurrenceParentId(),
			$event->getRecurrenceId()?->format( 'Y-m-d H:i:s' ),
			$event->getRecurrenceUntil()?->format( 'Y-m-d H:i:s' ),
			$event->getCategoryId(),
			$event->getStatus(),
			$event->isFeatured() ? 1 : 0,
			$event->isRegistrationEnabled() ? 1 : 0,
			$event->getRegistrationVisibility(),
			$event->getCapacity(),
			$event->getFeaturedImage(),
			$event->getExternalUrl(),
			$event->getOrganizer(),
			$event->getContactEmail(),
			$event->getContactPhone(),
			$event->getViewCount(),
			$now->format( 'Y-m-d H:i:s' ),
			$event->getId()
		] );

		return $event;
	}

	/**
	 * Delete event
	 */
	public function delete( Event $event ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM events WHERE id = ?" );
		return $stmt->execute( [ $event->getId() ] );
	}

	/**
	 * Check if event slug exists
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( "SELECT COUNT(*) FROM events WHERE slug = ? AND id != ?" );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( "SELECT COUNT(*) FROM events WHERE slug = ?" );
			$stmt->execute( [ $slug ] );
		}

		return $stmt->fetchColumn() > 0;
	}

	/**
	 * Increment view count
	 */
	public function incrementViewCount( Event $event ): void
	{
		$stmt = $this->_pdo->prepare( "UPDATE events SET view_count = view_count + 1 WHERE id = ?" );
		$stmt->execute( [ $event->getId() ] );
		$event->incrementViewCount();
	}

	/**
	 * Load category and creator relationships for an event
	 */
	private function loadRelations( Event $event ): void
	{
		// Load category
		if( $event->getCategoryId() )
		{
			$stmt = $this->_pdo->prepare( "SELECT * FROM event_categories WHERE id = ?" );
			$stmt->execute( [ $event->getCategoryId() ] );
			$categoryRow = $stmt->fetch();

			if( $categoryRow )
			{
				$category = EventCategory::fromArray( $categoryRow );
				$event->setCategory( $category );
			}
		}

		// Load creator
		$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE id = ?" );
		$stmt->execute( [ $event->getCreatedBy() ] );
		$userRow = $stmt->fetch();

		if( $userRow )
		{
			$creator = User::fromArray( $userRow );
			$event->setCreator( $creator );
		}
	}

	/**
	 * Hydrate a set of DB rows into Event models with relations loaded.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @return Event[]
	 */
	private function hydrateRows( array $rows ): array
	{
		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Sort a list of events by start date ascending, in place.
	 *
	 * @param Event[] $events
	 * @return void
	 */
	private function sortByStartAsc( array &$events ): void
	{
		usort( $events, fn( Event $a, Event $b ) => $a->getStartDate() <=> $b->getStartDate() );
	}

	/**
	 * Configured recurrence look-ahead horizon in months.
	 *
	 * @return int
	 */
	private function horizonMonths(): int
	{
		$recurrence = $this->_settings->get( 'events', 'recurrence' );

		if( is_array( $recurrence ) && !empty( $recurrence['horizon_months'] ) )
		{
			return max( 1, (int)$recurrence['horizon_months'] );
		}

		return self::DEFAULT_HORIZON_MONTHS;
	}

	/**
	 * End of the look-ahead window from a given start.
	 *
	 * @param DateTimeImmutable $from
	 * @return DateTimeImmutable
	 */
	private function horizonEnd( DateTimeImmutable $from ): DateTimeImmutable
	{
		return $from->add( new \DateInterval( 'P' . $this->horizonMonths() . 'M' ) );
	}

	/**
	 * Expand recurring masters into occurrences within the given range.
	 *
	 * @param DateTimeImmutable $rangeStart
	 * @param DateTimeImmutable $rangeEnd
	 * @param string $status
	 * @param string $extraSql Optional extra WHERE fragment (e.g. ' AND category_id = ?')
	 * @param array<int, mixed> $extraParams Parameters for $extraSql
	 * @return Event[]
	 */
	private function expandInRange(
		DateTimeImmutable $rangeStart,
		DateTimeImmutable $rangeEnd,
		string $status,
		string $extraSql = '',
		array $extraParams = []
	): array
	{
		$sql = "SELECT * FROM events
				WHERE rrule IS NOT NULL AND recurrence_parent_id IS NULL
				AND status = ?
				AND start_date <= ?
				AND (recurrence_until IS NULL OR recurrence_until >= ?)"
				. $extraSql;

		$params = array_merge(
			[ $status, $rangeEnd->format( 'Y-m-d H:i:s' ), $rangeStart->format( 'Y-m-d H:i:s' ) ],
			$extraParams
		);

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$masters = $this->hydrateRows( $stmt->fetchAll() );

		$occurrences = [];

		foreach( $masters as $master )
		{
			$expanded = $this->_expander->expand(
				$master,
				$rangeStart,
				$rangeEnd,
				$this->getExceptionStrings( $master->getId() ),
				$this->getOverrideDateStrings( $master->getId() )
			);

			foreach( $expanded as $occurrence )
			{
				$occurrences[] = $occurrence;
			}
		}

		return $occurrences;
	}

	/**
	 * Excluded occurrence start strings for a master ('Y-m-d H:i:s').
	 *
	 * @param int $masterId
	 * @return array<int, string>
	 */
	private function getExceptionStrings( int $masterId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT occurrence_date FROM event_recurrence_exceptions WHERE event_id = ?"
		);
		$stmt->execute( [ $masterId ] );

		return array_map(
			fn( $value ) => ( new DateTimeImmutable( (string)$value ) )->format( 'Y-m-d H:i:s' ),
			$stmt->fetchAll( PDO::FETCH_COLUMN ) ?: []
		);
	}

	/**
	 * Original occurrence start strings that have stored override rows.
	 *
	 * @param int $masterId
	 * @return array<int, string>
	 */
	private function getOverrideDateStrings( int $masterId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT recurrence_id FROM events WHERE recurrence_parent_id = ? AND recurrence_id IS NOT NULL"
		);
		$stmt->execute( [ $masterId ] );

		return array_map(
			fn( $value ) => ( new DateTimeImmutable( (string)$value ) )->format( 'Y-m-d H:i:s' ),
			$stmt->fetchAll( PDO::FETCH_COLUMN ) ?: []
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addException( int $eventId, DateTimeImmutable $occurrenceDate ): void
	{
		$formatted = $occurrenceDate->format( 'Y-m-d H:i:s' );

		$check = $this->_pdo->prepare(
			"SELECT COUNT(*) FROM event_recurrence_exceptions WHERE event_id = ? AND occurrence_date = ?"
		);
		$check->execute( [ $eventId, $formatted ] );

		if( $check->fetchColumn() > 0 )
		{
			return;
		}

		$stmt = $this->_pdo->prepare(
			"INSERT INTO event_recurrence_exceptions ( event_id, occurrence_date, created_at )
			VALUES ( ?, ?, ? )"
		);
		$stmt->execute( [ $eventId, $formatted, ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ) ] );
	}

	/**
	 * @inheritDoc
	 */
	public function removeException( int $eventId, DateTimeImmutable $occurrenceDate ): void
	{
		$stmt = $this->_pdo->prepare(
			"DELETE FROM event_recurrence_exceptions WHERE event_id = ? AND occurrence_date = ?"
		);
		$stmt->execute( [ $eventId, $occurrenceDate->format( 'Y-m-d H:i:s' ) ] );
	}

	/**
	 * @inheritDoc
	 */
	public function getExceptions( int $eventId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT occurrence_date FROM event_recurrence_exceptions WHERE event_id = ? ORDER BY occurrence_date ASC"
		);
		$stmt->execute( [ $eventId ] );

		return array_map(
			fn( $value ) => new DateTimeImmutable( (string)$value ),
			$stmt->fetchAll( PDO::FETCH_COLUMN ) ?: []
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getOverrides( int $masterId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events WHERE recurrence_parent_id = ? ORDER BY recurrence_id ASC"
		);
		$stmt->execute( [ $masterId ] );

		return $this->hydrateRows( $stmt->fetchAll() );
	}

	/**
	 * @inheritDoc
	 */
	public function findOverride( int $masterId, DateTimeImmutable $recurrenceId ): ?Event
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events WHERE recurrence_parent_id = ? AND recurrence_id = ? LIMIT 1"
		);
		$stmt->execute( [ $masterId, $recurrenceId->format( 'Y-m-d H:i:s' ) ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		$event = Event::fromArray( $row );
		$this->loadRelations( $event );

		return $event;
	}
}
