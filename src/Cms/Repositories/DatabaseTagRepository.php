<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Tag;
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
	private PDO $_PDO;

	/**
	 * Constructor
	 *
	 * @param array $DatabaseConfig Database configuration
	 * @throws Exception if adapter is not supported
	 */
	public function __construct( array $DatabaseConfig )
	{
		$adapter = $DatabaseConfig['adapter'] ?? 'sqlite';

		$dsn = match( $adapter )
		{
			'sqlite' => "sqlite:{$DatabaseConfig['name']}",
			'mysql' => sprintf(
				"mysql:host=%s;port=%s;dbname=%s;charset=%s",
				$DatabaseConfig['host'] ?? 'localhost',
				$DatabaseConfig['port'] ?? 3306,
				$DatabaseConfig['name'],
				$DatabaseConfig['charset'] ?? 'utf8mb4'
			),
			'pgsql' => sprintf(
				"pgsql:host=%s;port=%s;dbname=%s",
				$DatabaseConfig['host'] ?? 'localhost',
				$DatabaseConfig['port'] ?? 5432,
				$DatabaseConfig['name']
			),
			default => throw new Exception( "Unsupported database adapter: $adapter" )
		};

		$this->_PDO = new PDO(
			$dsn,
			$DatabaseConfig['user'] ?? null,
			$DatabaseConfig['pass'] ?? null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);
	}

	/**
	 * Find tag by ID
	 */
	public function findById( int $Id ): ?Tag
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM tags WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $Id ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
	}

	/**
	 * Find tag by slug
	 */
	public function findBySlug( string $Slug ): ?Tag
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM tags WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $Slug ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
	}

	/**
	 * Find tag by name
	 */
	public function findByName( string $Name ): ?Tag
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM tags WHERE name = ? LIMIT 1" );
		$stmt->execute( [ $Name ] );

		$row = $stmt->fetch();

		return $row ? Tag::fromArray( $row ) : null;
	}

	/**
	 * Create a new tag
	 */
	public function create( Tag $Tag ): Tag
	{
		// Check for duplicate slug
		if( $this->findBySlug( $Tag->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name
		if( $this->findByName( $Tag->getName() ) )
		{
			throw new Exception( 'Tag name already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$Tag->getName(),
			$Tag->getSlug(),
			$Tag->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$Tag->setId( (int)$this->_PDO->lastInsertId() );

		return $Tag;
	}

	/**
	 * Update an existing tag
	 */
	public function update( Tag $Tag ): bool
	{
		if( !$Tag->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current tag)
		$ExistingBySlug = $this->findBySlug( $Tag->getSlug() );
		if( $ExistingBySlug && $ExistingBySlug->getId() !== $Tag->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name (excluding current tag)
		$ExistingByName = $this->findByName( $Tag->getName() );
		if( $ExistingByName && $ExistingByName->getId() !== $Tag->getId() )
		{
			throw new Exception( 'Tag name already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"UPDATE tags SET
				name = ?,
				slug = ?,
				updated_at = ?
			WHERE id = ?"
		);

		return $stmt->execute([
			$Tag->getName(),
			$Tag->getSlug(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$Tag->getId()
		]);
	}

	/**
	 * Delete a tag
	 */
	public function delete( int $Id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$stmt = $this->_PDO->prepare( "DELETE FROM tags WHERE id = ?" );
		$stmt->execute( [ $Id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all tags
	 */
	public function all(): array
	{
		$stmt = $this->_PDO->query( "SELECT * FROM tags ORDER BY name ASC" );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Tag::fromArray( $row ), $rows );
	}

	/**
	 * Count total tags
	 */
	public function count(): int
	{
		$stmt = $this->_PDO->query( "SELECT COUNT(*) as total FROM tags" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Get tags with post count
	 */
	public function allWithPostCount(): array
	{
		$stmt = $this->_PDO->query(
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
