<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Tag;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed tag repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseTagRepository implements ITagRepository
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
		// Keep PDO for allWithPostCount() which uses a custom JOIN query
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find tag by ID
	 */
	public function findById( int $id ): ?Tag
	{
		return Tag::find( $id );
	}

	/**
	 * Find tag by slug
	 */
	public function findBySlug( string $slug ): ?Tag
	{
		return Tag::where( 'slug', $slug )->first();
	}

	/**
	 * Find tag by name
	 */
	public function findByName( string $name ): ?Tag
	{
		return Tag::where( 'name', $name )->first();
	}

	/**
	 * Create a new tag
	 */
	public function create( Tag $tag ): Tag
	{
		// Check for duplicate slug
		if( $this->findBySlug( $tag->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name
		if( $this->findByName( $tag->getName() ) )
		{
			throw new Exception( 'Tag name already exists' );
		}

		// Set timestamps explicitly (ORM doesn't use DB defaults)
		$now = new \DateTimeImmutable();
		if( !$tag->getCreatedAt() )
		{
			$tag->setCreatedAt( $now );
		}
		if( !$tag->getUpdatedAt() )
		{
			$tag->setUpdatedAt( $now );
		}

		// Use ORM create method - exclude id to let database handle auto-increment
		$data = $tag->toArray();
		if( isset( $data['id'] ) && $data['id'] === null )
		{
			unset( $data['id'] );
		}
		$createdTag = Tag::create( $data );

		// Fetch from database to get all fields
		return $this->findById( $createdTag->getId() );
	}

	/**
	 * Update an existing tag
	 */
	public function update( Tag $tag ): bool
	{
		if( !$tag->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current tag)
		$existingBySlug = $this->findBySlug( $tag->getSlug() );
		if( $existingBySlug && $existingBySlug->getId() !== $tag->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name (excluding current tag)
		$existingByName = $this->findByName( $tag->getName() );
		if( $existingByName && $existingByName->getId() !== $tag->getId() )
		{
			throw new Exception( 'Tag name already exists' );
		}

		// Update timestamp (database-independent approach)
		$tag->setUpdatedAt( new \DateTimeImmutable() );

		// Use ORM save method
		return $tag->save();
	}

	/**
	 * Delete a tag
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$deletedCount = Tag::query()->where( 'id', $id )->delete();

		return $deletedCount > 0;
	}

	/**
	 * Get all tags
	 */
	public function all(): array
	{
		return Tag::orderBy( 'name', 'ASC' )->all();
	}

	/**
	 * Count total tags
	 */
	public function count(): int
	{
		return Tag::query()->count();
	}

	/**
	 * Get tags with post count
	 */
	public function allWithPostCount(): array
	{
		// This method still uses raw SQL for the JOIN with aggregation
		// TODO: Add support for joins and aggregations to ORM
		$stmt = $this->_pdo->query(
			"SELECT t.*, COUNT(pt.post_id) as post_count
			FROM tags t
			LEFT JOIN post_tags pt ON t.id = pt.tag_id
			GROUP BY t.id
			ORDER BY t.name ASC"
		);

		$rows = $stmt->fetchAll();

		return array_map( function( $row ) {
			$tag = Tag::fromArray( $row );
			return [
				'tag' => $tag,
				'post_count' => (int)$row['post_count']
			];
		}, $rows );
	}

	/**
	 * Handle serialization for PHPUnit process isolation
	 */
	public function __sleep(): array
	{
		// Don't serialize PDO connection
		return [];
	}

	/**
	 * Handle unserialization for PHPUnit process isolation
	 */
	public function __wakeup(): void
	{
		// PDO will be re-initialized by test setup
	}
}
