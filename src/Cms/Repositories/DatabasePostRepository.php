<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed post repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabasePostRepository implements IPostRepository
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
		// Keep PDO for methods that need raw SQL queries (getByCategory, getByTag)
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find post by ID
	 */
	public function findById( int $id ): ?Post
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		$post = Post::fromArray( $row );
		$this->loadRelations( $post );

		return $post;
	}

	/**
	 * Find post by slug
	 */
	public function findBySlug( string $slug ): ?Post
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM posts WHERE slug = ?" );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		$post = Post::fromArray( $row );
		$this->loadRelations( $post );

		return $post;
	}

	/**
	 * Load categories and tags for a post
	 */
	private function loadRelations( Post $post ): void
	{
		// Load categories
		$stmt = $this->_pdo->prepare(
			"SELECT c.* FROM categories c
			INNER JOIN post_categories pc ON c.id = pc.category_id
			WHERE pc.post_id = ?"
		);
		$stmt->execute( [ $post->getId() ] );
		$categoryRows = $stmt->fetchAll();

		$categories = array_map(
			fn( $row ) => Category::fromArray( $row ),
			$categoryRows
		);
		$post->setCategories( $categories );

		// Load tags
		$stmt = $this->_pdo->prepare(
			"SELECT t.* FROM tags t
			INNER JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.post_id = ?"
		);
		$stmt->execute( [ $post->getId() ] );
		$tagRows = $stmt->fetchAll();

		$tags = array_map(
			fn( $row ) => Tag::fromArray( $row ),
			$tagRows
		);
		$post->setTags( $tags );
	}

	/**
	 * Create a new post
	 */
	public function create( Post $post ): Post
	{
		// Check for duplicate slug
		if( $this->findBySlug( $post->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Use transaction to ensure atomicity of post creation and relation syncing
		return Post::transaction( function() use ( $post ) {
			// Use ORM create method - only save the post data without relations
			$createdPost = Post::create( $post->toArray() );

			// Update the original post with the new ID
			$post->setId( $createdPost->getId() );

			// Sync categories using raw SQL (vendor ORM doesn't have relation() method yet)
			if( count( $post->getCategories() ) > 0 )
			{
				$categoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
				$this->syncCategories( $post->getId(), $categoryIds );
			}

			// Sync tags using raw SQL
			if( count( $post->getTags() ) > 0 )
			{
				$tagIds = array_map( fn( $t ) => $t->getId(), $post->getTags() );
				$this->syncTags( $post->getId(), $tagIds );
			}

			return $post;
		} );
	}

	/**
	 * Update an existing post
	 */
	public function update( Post $post ): bool
	{
		if( !$post->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current post)
		$existingBySlug = $this->findBySlug( $post->getSlug() );
		if( $existingBySlug && $existingBySlug->getId() !== $post->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		// Use transaction to ensure atomicity of post update and relation syncing
		return Post::transaction( function() use ( $post ) {
			// Update post using ORM (handles private properties via reflection)
			$post->setUpdatedAt( new \DateTimeImmutable() );
			$result = $post->save();

			// Sync categories
			$categoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
			$this->syncCategories( $post->getId(), $categoryIds );

			// Sync tags
			$tagIds = array_map( fn( $t ) => $t->getId(), $post->getTags() );
			$this->syncTags( $post->getId(), $tagIds );

			return $result;
		} );
	}

	/**
	 * Delete a post
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of relationships
		$deletedCount = Post::query()->where( 'id', $id )->delete();

		return $deletedCount > 0;
	}

	/**
	 * Get all posts
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array
	{
		$query = Post::query();

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
	 * Get posts by author
	 */
	public function getByAuthor( int $authorId, ?string $status = null ): array
	{
		$query = Post::query()->where( 'author_id', $authorId );

		if( $status )
		{
			$query->where( 'status', $status );
		}

		return $query->orderBy( 'created_at', 'DESC' )->get();
	}

	/**
	 * Get posts by category
	 */
	public function getByCategory( int $categoryId, ?string $status = null ): array
	{
		// This still uses raw SQL for the JOIN
		// TODO: Add JOIN support to ORM QueryBuilder
		$sql = "SELECT p.* FROM posts p
				INNER JOIN post_categories pc ON p.id = pc.post_id
				WHERE pc.category_id = ?";
		$params = [ $categoryId ];

		if( $status )
		{
			$sql .= " AND p.status = ?";
			$params[] = $status;
		}

		$sql .= " ORDER BY p.created_at DESC";

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Post::fromArray( $row ), $rows );
	}

	/**
	 * Get posts by tag
	 */
	public function getByTag( int $tagId, ?string $status = null ): array
	{
		// This still uses raw SQL for the JOIN
		// TODO: Add JOIN support to ORM QueryBuilder
		$sql = "SELECT p.* FROM posts p
				INNER JOIN post_tags pt ON p.id = pt.post_id
				WHERE pt.tag_id = ?";
		$params = [ $tagId ];

		if( $status )
		{
			$sql .= " AND p.status = ?";
			$params[] = $status;
		}

		$sql .= " ORDER BY p.created_at DESC";

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Post::fromArray( $row ), $rows );
	}

	/**
	 * Get published posts
	 */
	public function getPublished( int $limit = 0, int $offset = 0 ): array
	{
		return $this->all( Post::STATUS_PUBLISHED, $limit, $offset );
	}

	/**
	 * Get draft posts
	 */
	public function getDrafts(): array
	{
		return $this->all( Post::STATUS_DRAFT );
	}

	/**
	 * Get scheduled posts
	 */
	public function getScheduled(): array
	{
		return $this->all( Post::STATUS_SCHEDULED );
	}

	/**
	 * Count total posts
	 */
	public function count( ?string $status = null ): int
	{
		$query = Post::query();

		if( $status )
		{
			$query->where( 'status', $status );
		}

		return $query->count();
	}

	/**
	 * Increment post view count atomically
	 *
	 * Uses atomic SQL UPDATE via ORM to avoid race conditions under concurrent requests.
	 * The fetch-increment-save pattern would lose increments under high concurrency.
	 */
	public function incrementViewCount( int $id ): bool
	{
		// Use ORM's atomic increment to avoid race condition
		$rowsUpdated = Post::query()
			->where( 'id', $id )
			->increment( 'view_count', 1 );

		return $rowsUpdated > 0;
	}

	/**
	 * Sync categories for a post (removes old, adds new)
	 */
	private function syncCategories( int $postId, array $categoryIds ): void
	{
		// Delete existing categories
		$this->_pdo->prepare( "DELETE FROM post_categories WHERE post_id = ?" )
			->execute( [ $postId ] );

		// Insert new categories
		if( !empty( $categoryIds ) )
		{
			$stmt = $this->_pdo->prepare( "INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)" );
			$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
			foreach( $categoryIds as $categoryId )
			{
				$stmt->execute( [ $postId, $categoryId, $now ] );
			}
		}
	}

	/**
	 * Sync tags for a post (removes old, adds new)
	 */
	private function syncTags( int $postId, array $tagIds ): void
	{
		// Delete existing tags
		$this->_pdo->prepare( "DELETE FROM post_tags WHERE post_id = ?" )
			->execute( [ $postId ] );

		// Insert new tags
		if( !empty( $tagIds ) )
		{
			$stmt = $this->_pdo->prepare( "INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)" );
			$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
			foreach( $tagIds as $tagId )
			{
				$stmt->execute( [ $postId, $tagId, $now ] );
			}
		}
	}

	/**
	 * Attach categories to post
	 */
	public function attachCategories( int $postId, array $categoryIds ): bool
	{
		if( empty( $categoryIds ) )
		{
			return true;
		}

		$stmt = $this->_pdo->prepare( "INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)" );
		$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
		foreach( $categoryIds as $categoryId )
		{
			$stmt->execute( [ $postId, $categoryId, $now ] );
		}

		return true;
	}

	/**
	 * Detach all categories from post
	 */
	public function detachCategories( int $postId ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM post_categories WHERE post_id = ?" );
		$stmt->execute( [ $postId ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Attach tags to post
	 */
	public function attachTags( int $postId, array $tagIds ): bool
	{
		if( empty( $tagIds ) )
		{
			return true;
		}

		$stmt = $this->_pdo->prepare( "INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)" );
		$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
		foreach( $tagIds as $tagId )
		{
			$stmt->execute( [ $postId, $tagId, $now ] );
		}

		return true;
	}

	/**
	 * Detach all tags from post
	 */
	public function detachTags( int $postId ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM post_tags WHERE post_id = ?" );
		$stmt->execute( [ $postId ] );

		return $stmt->rowCount() > 0;
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
