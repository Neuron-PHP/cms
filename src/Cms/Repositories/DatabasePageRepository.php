<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Page;
use Neuron\Data\Settings\SettingManager;
use Exception;

/**
 * Database-backed page repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabasePageRepository implements IPageRepository
{
	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		// No longer need PDO - ORM is initialized in Bootstrap
	}

	/**
	 * Find page by ID
	 */
	public function findById( int $id ): ?Page
	{
		// Use eager loading for author
		return Page::with( 'author' )->find( $id );
	}

	/**
	 * Find page by slug
	 */
	public function findBySlug( string $slug ): ?Page
	{
		// Use eager loading for author
		return Page::with( 'author' )->where( 'slug', $slug )->first();
	}

	/**
	 * Create a new page
	 */
	public function create( Page $page ): Page
	{
		// Check for duplicate slug
		if( $this->findBySlug( $page->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Set timestamps explicitly (ORM doesn't use DB defaults)
		$now = new \DateTimeImmutable();
		if( !$page->getCreatedAt() )
		{
			$page->setCreatedAt( $now );
		}
		if( !$page->getUpdatedAt() )
		{
			$page->setUpdatedAt( $now );
		}

		// Use ORM create method - exclude id to let database handle auto-increment
		$data = $page->toArray();
		if( array_key_exists( 'id', $data ) && $data['id'] === null )
		{
			unset( $data['id'] );
		}
		$createdPage = Page::create( $data );

		// Fetch from database to get all fields
		return $this->findById( $createdPage->getId() );
	}

	/**
	 * Update an existing page
	 */
	public function update( Page $page ): bool
	{
		if( !$page->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current page)
		$existingBySlug = $this->findBySlug( $page->getSlug() );
		if( $existingBySlug && $existingBySlug->getId() !== $page->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Update timestamp (database-independent approach)
		$page->setUpdatedAt( new \DateTimeImmutable() );

		// Use ORM save method
		return $page->save();
	}

	/**
	 * Delete a page
	 */
	public function delete( int $id ): bool
	{
		$deletedCount = Page::query()->where( 'id', $id )->delete();

		return $deletedCount > 0;
	}

	/**
	 * Get all pages
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array
	{
		$query = Page::query();

		if( $status )
		{
			$query->where( 'status', $status );
		}

		$query->orderBy( 'created_at', 'DESC' );

		if( $limit > 0 )
		{
			$query->limit( $limit )->offset( $offset );
		}

		return $query->get();
	}

	/**
	 * Get published pages
	 */
	public function getPublished( int $limit = 0, int $offset = 0 ): array
	{
		return $this->all( Page::STATUS_PUBLISHED, $limit, $offset );
	}

	/**
	 * Get draft pages
	 */
	public function getDrafts(): array
	{
		return $this->all( Page::STATUS_DRAFT );
	}

	/**
	 * Get pages by author
	 */
	public function getByAuthor( int $authorId, ?string $status = null ): array
	{
		$query = Page::query()->where( 'author_id', $authorId );

		if( $status )
		{
			$query->where( 'status', $status );
		}

		return $query->orderBy( 'created_at', 'DESC' )->get();
	}

	/**
	 * Count total pages
	 */
	public function count( ?string $status = null ): int
	{
		$query = Page::query();

		if( $status )
		{
			$query->where( 'status', $status );
		}

		return $query->count();
	}

	/**
	 * Increment page view count
	 *
	 * Uses atomic UPDATE to avoid race condition under concurrent requests.
	 */
	public function incrementViewCount( int $id ): bool
	{
		// Use ORM's atomic increment to avoid race condition
		$rowsUpdated = Page::query()
			->where( 'id', $id )
			->increment( 'view_count', 1 );

		return $rowsUpdated > 0;
	}
}
