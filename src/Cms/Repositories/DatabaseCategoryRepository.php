<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Category;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed category repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseCategoryRepository implements ICategoryRepository
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
	 * Find category by ID
	 */
	public function findById( int $Id ): ?Category
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM categories WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $Id ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $Slug ): ?Category
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM categories WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $Slug ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Find category by name
	 */
	public function findByName( string $Name ): ?Category
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM categories WHERE name = ? LIMIT 1" );
		$stmt->execute( [ $Name ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Create a new category
	 */
	public function create( Category $Category ): Category
	{
		// Check for duplicate slug
		if( $this->findBySlug( $Category->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name
		if( $this->findByName( $Category->getName() ) )
		{
			throw new Exception( 'Category name already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO categories (name, slug, description, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$Category->getName(),
			$Category->getSlug(),
			$Category->getDescription(),
			$Category->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$Category->setId( (int)$this->_PDO->lastInsertId() );

		return $Category;
	}

	/**
	 * Update an existing category
	 */
	public function update( Category $Category ): bool
	{
		if( !$Category->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current category)
		$ExistingBySlug = $this->findBySlug( $Category->getSlug() );
		if( $ExistingBySlug && $ExistingBySlug->getId() !== $Category->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name (excluding current category)
		$ExistingByName = $this->findByName( $Category->getName() );
		if( $ExistingByName && $ExistingByName->getId() !== $Category->getId() )
		{
			throw new Exception( 'Category name already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"UPDATE categories SET
				name = ?,
				slug = ?,
				description = ?,
				updated_at = ?
			WHERE id = ?"
		);

		return $stmt->execute([
			$Category->getName(),
			$Category->getSlug(),
			$Category->getDescription(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$Category->getId()
		]);
	}

	/**
	 * Delete a category
	 */
	public function delete( int $Id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$stmt = $this->_PDO->prepare( "DELETE FROM categories WHERE id = ?" );
		$stmt->execute( [ $Id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all categories
	 */
	public function all(): array
	{
		$stmt = $this->_PDO->query( "SELECT * FROM categories ORDER BY name ASC" );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Category::fromArray( $row ), $rows );
	}

	/**
	 * Count total categories
	 */
	public function count(): int
	{
		$stmt = $this->_PDO->query( "SELECT COUNT(*) as total FROM categories" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Get categories with post count
	 */
	public function allWithPostCount(): array
	{
		$stmt = $this->_PDO->query(
			"SELECT c.*, COUNT(pc.post_id) as post_count
			FROM categories c
			LEFT JOIN post_categories pc ON c.id = pc.category_id
			GROUP BY c.id
			ORDER BY c.name ASC"
		);

		$rows = $stmt->fetchAll();

		return array_map( function( $row ) {
			$category = Category::fromArray( $row );
			return [
				'category' => $category,
				'post_count' => (int)$row['post_count']
			];
		}, $rows );
	}
}
