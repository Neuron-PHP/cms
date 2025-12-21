<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\User;
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
	private PDO $_pdo;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Get all events
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( "SELECT * FROM events ORDER BY start_date DESC" );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
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
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE category_id = ? AND status = ?
			ORDER BY start_date ASC"
		);
		$stmt->execute( [ $categoryId, $status ] );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Get upcoming events
	 */
	public function getUpcoming( ?int $limit = null, string $status = 'published' ): array
	{
		$sql = "SELECT * FROM events
				WHERE start_date >= ? AND status = ?
				ORDER BY start_date ASC";

		if( $limit )
		{
			$sql .= " LIMIT " . (int)$limit;
		}

		$stmt = $this->_pdo->prepare( $sql );
		$now = new DateTimeImmutable();
		$stmt->execute( [ $now->format( 'Y-m-d H:i:s' ), $status ] );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Get past events
	 */
	public function getPast( ?int $limit = null, string $status = 'published' ): array
	{
		$sql = "SELECT * FROM events
				WHERE (end_date IS NOT NULL AND end_date < ?)
					OR (end_date IS NULL AND start_date < ?)
				AND status = ?
				ORDER BY start_date DESC";

		if( $limit )
		{
			$sql .= " LIMIT " . (int)$limit;
		}

		$stmt = $this->_pdo->prepare( $sql );
		$now = new DateTimeImmutable();
		$nowFormatted = $now->format( 'Y-m-d H:i:s' );
		$stmt->execute( [ $nowFormatted, $nowFormatted, $status ] );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Get events by date range
	 */
	public function getByDateRange( DateTimeImmutable $startDate, DateTimeImmutable $endDate, string $status = 'published' ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE (
				(start_date >= ? AND start_date <= ?)
				OR (end_date >= ? AND end_date <= ?)
				OR (start_date <= ? AND end_date >= ?)
			)
			AND status = ?
			ORDER BY start_date ASC"
		);

		$startFormatted = $startDate->format( 'Y-m-d H:i:s' );
		$endFormatted = $endDate->format( 'Y-m-d H:i:s' );

		$stmt->execute( [
			$startFormatted, $endFormatted,
			$startFormatted, $endFormatted,
			$startFormatted, $endFormatted,
			$status
		] );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Get events by creator
	 */
	public function getByCreator( int $userId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM events
			WHERE created_by = ?
			ORDER BY start_date DESC"
		);
		$stmt->execute( [ $userId ] );
		$rows = $stmt->fetchAll();

		return array_map( function( $row )
		{
			$event = Event::fromArray( $row );
			$this->loadRelations( $event );
			return $event;
		}, $rows );
	}

	/**
	 * Create new event
	 */
	public function create( Event $event ): Event
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO events (
				title, slug, description, content_raw, location, start_date, end_date,
				all_day, category_id, status, featured_image, organizer, contact_email,
				contact_phone, created_by, view_count, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
			$event->getCategoryId(),
			$event->getStatus(),
			$event->getFeaturedImage(),
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
				start_date = ?, end_date = ?, all_day = ?, category_id = ?, status = ?,
				featured_image = ?, organizer = ?, contact_email = ?, contact_phone = ?,
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
			$event->getCategoryId(),
			$event->getStatus(),
			$event->getFeaturedImage(),
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
}
