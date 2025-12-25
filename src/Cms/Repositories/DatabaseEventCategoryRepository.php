<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\EventCategory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed event category repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseEventCategoryRepository implements IEventCategoryRepository
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

		// Set PDO connection on Model class for ORM queries
		EventCategory::setPdo( $this->_pdo );
	}

	/**
	 * Get all event categories
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( "SELECT * FROM event_categories ORDER BY name ASC" );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => EventCategory::fromArray( $row ), $rows );
	}

	/**
	 * Find category by ID
	 */
	public function findById( int $id ): ?EventCategory
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM event_categories WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return EventCategory::fromArray( $row );
	}

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $slug ): ?EventCategory
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM event_categories WHERE slug = ?" );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return EventCategory::fromArray( $row );
	}

	/**
	 * Find categories by IDs
	 */
	public function findByIds( array $ids ): array
	{
		if( empty( $ids ) )
		{
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '?' ) );
		$stmt = $this->_pdo->prepare( "SELECT * FROM event_categories WHERE id IN ($placeholders)" );
		$stmt->execute( $ids );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => EventCategory::fromArray( $row ), $rows );
	}

	/**
	 * Create new category
	 */
	public function create( EventCategory $category ): EventCategory
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO event_categories (name, slug, color, description, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?)"
		);

		$now = new DateTimeImmutable();
		$category->setCreatedAt( $now );
		$category->setUpdatedAt( $now );

		$stmt->execute( [
			$category->getName(),
			$category->getSlug(),
			$category->getColor(),
			$category->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$category->setId( (int)$this->_pdo->lastInsertId() );

		return $category;
	}

	/**
	 * Update category
	 */
	public function update( EventCategory $category ): EventCategory
	{
		$stmt = $this->_pdo->prepare(
			"UPDATE event_categories
			SET name = ?, slug = ?, color = ?, description = ?, updated_at = ?
			WHERE id = ?"
		);

		$now = new DateTimeImmutable();
		$category->setUpdatedAt( $now );

		$stmt->execute( [
			$category->getName(),
			$category->getSlug(),
			$category->getColor(),
			$category->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$category->getId()
		] );

		return $category;
	}

	/**
	 * Delete category
	 */
	public function delete( EventCategory $category ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM event_categories WHERE id = ?" );
		return $stmt->execute( [ $category->getId() ] );
	}

	/**
	 * Check if category slug exists
	 */
	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( "SELECT COUNT(*) FROM event_categories WHERE slug = ? AND id != ?" );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( "SELECT COUNT(*) FROM event_categories WHERE slug = ?" );
			$stmt->execute( [ $slug ] );
		}

		return $stmt->fetchColumn() > 0;
	}
}
