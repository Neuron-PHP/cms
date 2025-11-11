<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Tag;
use Neuron\Data\Setting\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed tag repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
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
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find tag by ID
	 */
	public function findById( int $id ): ?Tag
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM tags WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $id ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
	}

	/**
	 * Find tag by slug
	 */
	public function findBySlug( string $slug ): ?Tag
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM tags WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $slug ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
	}

	/**
	 * Find tag by name
	 */
	public function findByName( string $name ): ?Tag
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM tags WHERE name = ? LIMIT 1" );
		$stmt->execute( [ $name ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$tag->getName(),
			$tag->getSlug(),
			$tag->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$tag->setId( (int)$this->_pdo->lastInsertId() );

		return $tag;
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

		$stmt = $this->_pdo->prepare(
			"UPDATE tags SET
				name = ?,
				slug = ?,
				updated_at = ?
			WHERE id = ?"
		);

		return $stmt->execute([
			$tag->getName(),
			$tag->getSlug(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$tag->getId()
		]);
	}

	/**
	 * Delete a tag
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$stmt = $this->_pdo->prepare( "DELETE FROM tags WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all tags
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( "SELECT * FROM tags ORDER BY name ASC" );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Tag::fromArray( $row ), $rows );
	}

	/**
	 * Count total tags
	 */
	public function count(): int
	{
		$stmt = $this->_pdo->query( "SELECT COUNT(*) as total FROM tags" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Get tags with post count
	 */
	public function allWithPostCount(): array
	{
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
}
