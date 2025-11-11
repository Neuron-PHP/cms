<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Category;
use Neuron\Data\Setting\SettingManager;
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
	 * Find category by ID
	 */
	public function findById( int $id ): ?Category
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM categories WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $id ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $slug ): ?Category
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM categories WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $slug ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Find category by name
	 */
	public function findByName( string $name ): ?Category
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM categories WHERE name = ? LIMIT 1" );
		$stmt->execute( [ $name ] );

		$row = $stmt->fetch();

		return $row ? Category::fromArray( $row ) : null;
	}

	/**
	 * Find multiple categories by IDs
	 *
	 * @param array $ids Array of category IDs
	 * @return Category[]
	 */
	public function findByIds( array $ids ): array
	{
		if( empty( $ids ) )
		{
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '?' ) );
		$stmt = $this->_pdo->prepare( "SELECT * FROM categories WHERE id IN ($placeholders)" );
		$stmt->execute( $ids );

		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Category::fromArray( $row ), $rows );
	}

	/**
	 * Create a new category
	 */
	public function create( Category $category ): Category
	{
		// Check for duplicate slug
		if( $this->findBySlug( $category->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name
		if( $this->findByName( $category->getName() ) )
		{
			throw new Exception( 'Category name already exists' );
		}

		$stmt = $this->_pdo->prepare(
			"INSERT INTO categories (name, slug, description, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$category->getName(),
			$category->getSlug(),
			$category->getDescription(),
			$category->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$category->setId( (int)$this->_pdo->lastInsertId() );

		return $category;
	}

	/**
	 * Update an existing category
	 */
	public function update( Category $category ): bool
	{
		if( !$category->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current category)
		$existingBySlug = $this->findBySlug( $category->getSlug() );
		if( $existingBySlug && $existingBySlug->getId() !== $category->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Check for duplicate name (excluding current category)
		$existingByName = $this->findByName( $category->getName() );
		if( $existingByName && $existingByName->getId() !== $category->getId() )
		{
			throw new Exception( 'Category name already exists' );
		}

		$stmt = $this->_pdo->prepare(
			"UPDATE categories SET
				name = ?,
				slug = ?,
				description = ?,
				updated_at = ?
			WHERE id = ?"
		);

		return $stmt->execute([
			$category->getName(),
			$category->getSlug(),
			$category->getDescription(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$category->getId()
		]);
	}

	/**
	 * Delete a category
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$stmt = $this->_pdo->prepare( "DELETE FROM categories WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all categories
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( "SELECT * FROM categories ORDER BY name ASC" );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Category::fromArray( $row ), $rows );
	}

	/**
	 * Count total categories
	 */
	public function count(): int
	{
		$stmt = $this->_pdo->query( "SELECT COUNT(*) as total FROM categories" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Get categories with post count
	 */
	public function allWithPostCount(): array
	{
		$stmt = $this->_pdo->query(
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
