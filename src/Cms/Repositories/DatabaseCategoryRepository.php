<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Category;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed category repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
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
		// Keep PDO for allWithPostCount() which uses a custom JOIN query
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find category by ID
	 */
	public function findById( int $id ): ?Category
	{
		return Category::find( $id );
	}

	/**
	 * Find category by slug
	 */
	public function findBySlug( string $slug ): ?Category
	{
		return Category::where( 'slug', $slug )->first();
	}

	/**
	 * Find category by name
	 */
	public function findByName( string $name ): ?Category
	{
		return Category::where( 'name', $name )->first();
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

		return Category::whereIn( 'id', $ids )->get();
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

		// Set timestamps explicitly (ORM doesn't use DB defaults)
		$now = new \DateTimeImmutable();
		if( !$category->getCreatedAt() )
		{
			$category->setCreatedAt( $now );
		}
		if( !$category->getUpdatedAt() )
		{
			$category->setUpdatedAt( $now );
		}

		// Use ORM save method on new instance
		$newCategory = new Category();
		foreach( $category->toArray() as $key => $value )
		{
			// Skip id and all DateTimeImmutable fields (toArray() returns strings, setters expect objects)
			if( in_array( $key, [ 'id', 'created_at', 'updated_at' ] ) )
			{
				continue;
			}

			$setter = 'set' . str_replace( '_', '', ucwords( $key, '_' ) );
			if( method_exists( $newCategory, $setter ) )
			{
				$newCategory->$setter( $value );
			}
		}

		// Set DateTimeImmutable fields from original object
		$newCategory->setCreatedAt( $category->getCreatedAt() );
		$newCategory->setUpdatedAt( $category->getUpdatedAt() );

		$newCategory->save();

		return $this->findById( $newCategory->getId() );
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

		// Update timestamp (database-independent approach)
		$category->setUpdatedAt( new \DateTimeImmutable() );

		// Use ORM save method
		return $category->save();
	}

	/**
	 * Delete a category
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of post relationships
		$deletedCount = Category::query()->where( 'id', $id )->delete();

		return $deletedCount > 0;
	}

	/**
	 * Get all categories
	 */
	public function all(): array
	{
		return Category::orderBy( 'name', 'ASC' )->all();
	}

	/**
	 * Count total categories
	 */
	public function count(): int
	{
		return Category::query()->count();
	}

	/**
	 * Get categories with post count
	 */
	public function allWithPostCount(): array
	{
		// This method still uses raw SQL for the JOIN with aggregation
		// TODO: Add support for joins and aggregations to ORM
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
