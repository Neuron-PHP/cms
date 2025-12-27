<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\Traits\ManagesTimestamps;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use RuntimeException;
use Neuron\Cms\Enums\ContentStatus;

/**
 * Database-backed post repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabasePostRepository implements IPostRepository
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
		// Keep PDO for transaction-scoped sync operations
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		// Set PDO connection on Model class for ORM queries
		Post::setPdo( $this->_pdo );
	}

	/**
	 * Find post by ID
	 */
	public function findById( int $id ): ?Post
	{
		// Use ORM eager loading to avoid N+1 queries
		return Post::query()
			->with( ['categories', 'tags'] )
			->where( 'id', $id )
			->first();
	}

	/**
	 * Find post by slug
	 */
	public function findBySlug( string $slug ): ?Post
	{
		// Use ORM eager loading to avoid N+1 queries
		return Post::query()
			->with( ['categories', 'tags'] )
			->where( 'slug', $slug )
			->first();
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

		// Set timestamps
		$this->ensureTimestamps( $post );

		// Use transaction to ensure atomicity of post creation and relation syncing
		return Post::transaction( function() use ( $post ) {
			// Save original categories and tags before creating
			$categories = $post->getCategories();
			$tags = $post->getTags();

			// Get the ORM's PDO for sync operations (same connection as transaction)
			$pdo = $this->getOrmPdo();

			// Save the post using ORM
			$post->save();

			// Verify the post was saved with an ID
			$postId = $post->getId();
			if( $postId === null )
			{
				throw new RuntimeException( 'Failed to save Post: Entity ID is null after save operation' );
			}

			// Sync categories using raw SQL (vendor ORM doesn't have relation() method yet)
			if( count( $categories ) > 0 )
			{
				$categoryIds = array_map( fn( $c ) => $c->getId(), $categories );
				$this->syncCategoriesWithPdo( $pdo, $postId, $categoryIds );
			}

			// Sync tags using raw SQL
			if( count( $tags ) > 0 )
			{
				$tagIds = array_map( fn( $t ) => $t->getId(), $tags );
				$this->syncTagsWithPdo( $pdo, $postId, $tagIds );
			}

			// Use ORM's find() to get DB-set values within the same transaction
			// Can't use $this->findById() because it uses a different PDO connection
			$refreshedPost = Post::find( $postId );

			if( $refreshedPost === null )
			{
				throw new RuntimeException( "Failed to retrieve Post after save: Entity with ID {$postId} not found in database" );
			}

			// Load relations manually since we're inside the transaction
			$this->loadRelationsWithPdo( $pdo, $refreshedPost );

			return $refreshedPost;
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

			// Get the ORM's PDO for sync operations (same connection as transaction)
			$pdo = $this->getOrmPdo();

			// Sync categories
			$categoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
			$this->syncCategoriesWithPdo( $pdo, $post->getId(), $categoryIds );

			// Sync tags
			$tagIds = array_map( fn( $t ) => $t->getId(), $post->getTags() );
			$this->syncTagsWithPdo( $pdo, $post->getId(), $tagIds );

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
		// Use ORM JOIN support instead of raw SQL
		$query = Post::query()
			->select( ['posts.*'] )
			->join( 'post_categories', 'posts.id', '=', 'post_categories.post_id' )
			->where( 'post_categories.category_id', $categoryId );

		if( $status )
		{
			$query->where( 'posts.status', $status );
		}

		return $query->orderBy( 'posts.created_at', 'DESC' )->get();
	}

	/**
	 * Get posts by tag
	 */
	public function getByTag( int $tagId, ?string $status = null ): array
	{
		// Use ORM JOIN support instead of raw SQL
		$query = Post::query()
			->select( ['posts.*'] )
			->join( 'post_tags', 'posts.id', '=', 'post_tags.post_id' )
			->where( 'post_tags.tag_id', $tagId );

		if( $status )
		{
			$query->where( 'posts.status', $status );
		}

		return $query->orderBy( 'posts.created_at', 'DESC' )->get();
	}

	/**
	 * Get published posts
	 */
	public function getPublished( int $limit = 0, int $offset = 0 ): array
	{
		return $this->all( ContentStatus::PUBLISHED->value, $limit, $offset );
	}

	/**
	 * Get draft posts
	 */
	public function getDrafts(): array
	{
		return $this->all( ContentStatus::DRAFT->value );
	}

	/**
	 * Get scheduled posts
	 */
	public function getScheduled(): array
	{
		return $this->all( ContentStatus::SCHEDULED->value );
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
			->increment( 'view_count', 1, [
				'updated_at' => ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
			]);

		return $rowsUpdated > 0;
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
	 * Get the PDO connection from the ORM
	 *
	 * This method uses reflection to access the static PDO connection stored in the Model base class.
	 * The PDO connection is needed for transaction-scoped operations within Model::transaction().
	 *
	 * @return PDO The PDO connection used by the ORM
	 * @throws RuntimeException If the PDO connection cannot be retrieved or is not available
	 */
	private function getOrmPdo(): PDO
	{
		try
		{
			$reflectionClass = new \ReflectionClass( Post::class );

			// Check if the _pdo property exists on the Post class or its parent
			if( !$reflectionClass->hasProperty( '_pdo' ) )
			{
				throw new RuntimeException(
					'ORM PDO connection not available: Post class does not have a $_pdo property. ' .
					'This may indicate an ORM version incompatibility or configuration issue.'
				);
			}

			$property = $reflectionClass->getProperty( '_pdo' );
			$property->setAccessible( true );

			// Get the value - for static properties, pass null; for instance properties, pass an instance
			$value = $property->isStatic()
				? $property->getValue()
				: $property->getValue( null );

			// Verify we got a PDO instance
			if( !( $value instanceof PDO ) )
			{
				$type = is_object( $value ) ? get_class( $value ) : gettype( $value );
				throw new RuntimeException(
					"ORM PDO connection is not valid: Expected PDO instance, got {$type}. " .
					'Ensure Model::setPdo() has been called before creating posts.'
				);
			}

			return $value;
		}
		catch( \ReflectionException $e )
		{
			throw new RuntimeException(
				'Failed to access ORM PDO connection via reflection: ' . $e->getMessage() .
				'. This may indicate an ORM structure change or incompatible version.',
				0,
				$e
			);
		}
	}

	/**
	 * Sync categories using a specific PDO connection
	 *
	 * @param PDO $pdo
	 * @param int $postId
	 * @param array $categoryIds
	 */
	private function syncCategoriesWithPdo( PDO $pdo, int $postId, array $categoryIds ): void
	{
		// Delete existing categories
		$pdo->prepare( "DELETE FROM post_categories WHERE post_id = ?" )
			->execute( [ $postId ] );

		// Insert new categories
		if( !empty( $categoryIds ) )
		{
			$stmt = $pdo->prepare( "INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)" );
			$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
			foreach( $categoryIds as $categoryId )
			{
				$stmt->execute( [ $postId, $categoryId, $now ] );
			}
		}
	}

	/**
	 * Sync tags using a specific PDO connection
	 *
	 * @param PDO $pdo
	 * @param int $postId
	 * @param array $tagIds
	 */
	private function syncTagsWithPdo( PDO $pdo, int $postId, array $tagIds ): void
	{
		// Delete existing tags
		$pdo->prepare( "DELETE FROM post_tags WHERE post_id = ?" )
			->execute( [ $postId ] );

		// Insert new tags
		if( !empty( $tagIds ) )
		{
			$stmt = $pdo->prepare( "INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)" );
			$now = ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' );
			foreach( $tagIds as $tagId )
			{
				$stmt->execute( [ $postId, $tagId, $now ] );
			}
		}
	}

	/**
	 * Load relations using a specific PDO connection
	 *
	 * @param PDO $pdo
	 * @param Post $post
	 */
	private function loadRelationsWithPdo( PDO $pdo, Post $post ): void
	{
		// Load categories
		$stmt = $pdo->prepare(
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
		$stmt = $pdo->prepare(
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
