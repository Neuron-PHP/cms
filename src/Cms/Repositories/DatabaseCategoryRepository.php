<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\Traits\ManagesTimestamps;
use Neuron\Cms\Exceptions\DuplicateEntityException;
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
	use ManagesTimestamps;

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

		// Set PDO connection on Model class for ORM queries
		Category::setPdo( $this->_pdo );
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
			throw new DuplicateEntityException( 'Category', 'slug', $category->getSlug() );
		}

		// Check for duplicate name
		if( $this->findByName( $category->getName() ) )
		{
			throw new DuplicateEntityException( 'Category', 'name', $category->getName() );
		}

		// Set timestamps, save, and refresh with null-safety check
		return $this->createEntity(
			$category,
			fn( int $id ) => $this->findById( $id ),
			'Category'
		);
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
			throw new DuplicateEntityException( 'Category', 'slug', $category->getSlug() );
		}

		// Check for duplicate name (excluding current category)
		$existingByName = $this->findByName( $category->getName() );
		if( $existingByName && $existingByName->getId() !== $category->getId() )
		{
			throw new DuplicateEntityException( 'Category', 'name', $category->getName() );
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
		$rows = Category::query()
			->select( ['categories.*', 'COUNT(post_categories.post_id) as post_count'] )
			->leftJoin( 'post_categories', 'categories.id', '=', 'post_categories.category_id' )
			->groupBy( 'categories.id' )
			->orderBy( 'categories.name' )
			->getRaw();

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
