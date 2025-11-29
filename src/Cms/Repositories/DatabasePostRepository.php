<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\User;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed post repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
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
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find post by ID
	 */
	public function findById( int $id ): ?Post
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM posts WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $id ] );

		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return $this->mapRowToPost( $row );
	}

	/**
	 * Find post by slug
	 */
	public function findBySlug( string $slug ): ?Post
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM posts WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $slug ] );

		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return $this->mapRowToPost( $row );
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO posts (
				title, slug, body, excerpt, featured_image, author_id,
				status, published_at, view_count, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$post->getTitle(),
			$post->getSlug(),
			$post->getBody(),
			$post->getExcerpt(),
			$post->getFeaturedImage(),
			$post->getAuthorId(),
			$post->getStatus(),
			$post->getPublishedAt() ? $post->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$post->getViewCount(),
			$post->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$post->setId( (int)$this->_pdo->lastInsertId() );

		// Handle categories
		if( count( $post->getCategories() ) > 0 )
		{
			$categoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
			$this->attachCategories( $post->getId(), $categoryIds );
		}

		// Handle tags
		if( count( $post->getTags() ) > 0 )
		{
			$tagIds = array_map( fn( $t ) => $t->getId(), $post->getTags() );
			$this->attachTags( $post->getId(), $tagIds );
		}

		return $post;
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

		$stmt = $this->_pdo->prepare(
			"UPDATE posts SET
				title = ?,
				slug = ?,
				body = ?,
				excerpt = ?,
				featured_image = ?,
				author_id = ?,
				status = ?,
				published_at = ?,
				view_count = ?,
				updated_at = ?
			WHERE id = ?"
		);

		$result = $stmt->execute([
			$post->getTitle(),
			$post->getSlug(),
			$post->getBody(),
			$post->getExcerpt(),
			$post->getFeaturedImage(),
			$post->getAuthorId(),
			$post->getStatus(),
			$post->getPublishedAt() ? $post->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$post->getViewCount(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$post->getId()
		]);

		// Update categories
		$this->detachCategories( $post->getId() );
		if( count( $post->getCategories() ) > 0 )
		{
			$categoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
			$this->attachCategories( $post->getId(), $categoryIds );
		}

		// Update tags
		$this->detachTags( $post->getId() );
		if( count( $post->getTags() ) > 0 )
		{
			$tagIds = array_map( fn( $t ) => $t->getId(), $post->getTags() );
			$this->attachTags( $post->getId(), $tagIds );
		}

		return $result;
	}

	/**
	 * Delete a post
	 */
	public function delete( int $id ): bool
	{
		// Foreign key constraints will handle cascade delete of relationships
		$stmt = $this->_pdo->prepare( "DELETE FROM posts WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all posts
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array
	{
		$sql = "SELECT * FROM posts";
		$params = [];

		if( $status )
		{
			$sql .= " WHERE status = ?";
			$params[] = $status;
		}

		$sql .= " ORDER BY created_at DESC";

		if( $limit > 0 )
		{
			$sql .= " LIMIT ? OFFSET ?";
			$params[] = $limit;
			$params[] = $offset;
		}

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by author
	 */
	public function getByAuthor( int $authorId, ?string $status = null ): array
	{
		$sql = "SELECT * FROM posts WHERE author_id = ?";
		$params = [ $authorId ];

		if( $status )
		{
			$sql .= " AND status = ?";
			$params[] = $status;
		}

		$sql .= " ORDER BY created_at DESC";

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by category
	 */
	public function getByCategory( int $categoryId, ?string $status = null ): array
	{
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

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by tag
	 */
	public function getByTag( int $tagId, ?string $status = null ): array
	{
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

		return array_map( [ $this, 'mapRowToPost' ], $rows );
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
		$sql = "SELECT COUNT(*) as total FROM posts";
		$params = [];

		if( $status )
		{
			$sql .= " WHERE status = ?";
			$params[] = $status;
		}

		$stmt = $this->_pdo->prepare( $sql );
		$stmt->execute( $params );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Increment post view count
	 */
	public function incrementViewCount( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( "UPDATE posts SET view_count = view_count + 1 WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)"
		);

		foreach( $categoryIds as $categoryId )
		{
			$stmt->execute([
				$postId,
				$categoryId,
				(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
			]);
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

		return true;
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)"
		);

		foreach( $tagIds as $tagId )
		{
			$stmt->execute([
				$postId,
				$tagId,
				(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
			]);
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

		return true;
	}

	/**
	 * Map database row to Post object
	 *
	 * @param array $row Database row
	 * @return Post
	 */
	private function mapRowToPost( array $row ): Post
	{
		$data = [
			'id' => (int)$row['id'],
			'title' => $row['title'],
			'slug' => $row['slug'],
			'body' => $row['body'],
			'excerpt' => $row['excerpt'],
			'featured_image' => $row['featured_image'],
			'author_id' => (int)$row['author_id'],
			'status' => $row['status'],
			'view_count' => (int)$row['view_count'],
			'published_at' => $row['published_at'] ?? null,
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'] ?? null,
		];

		$post = Post::fromArray( $data );

		// Load relationships
		$post->setAuthor( $this->loadAuthor( $post->getAuthorId() ) );
		$post->setCategories( $this->loadCategories( $post->getId() ) );
		$post->setTags( $this->loadTags( $post->getId() ) );

		return $post;
	}

	/**
	 * Load categories for a post
	 *
	 * @param int $postId
	 * @return Category[]
	 */
	private function loadCategories( int $postId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT c.* FROM categories c
			INNER JOIN post_categories pc ON c.id = pc.category_id
			WHERE pc.post_id = ?
			ORDER BY c.name ASC"
		);
		$stmt->execute( [ $postId ] );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Category::fromArray( $row ), $rows );
	}

	/**
	 * Load tags for a post
	 *
	 * @param int $postId
	 * @return Tag[]
	 */
	private function loadTags( int $postId ): array
	{
		$stmt = $this->_pdo->prepare(
			"SELECT t.* FROM tags t
			INNER JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.post_id = ?
			ORDER BY t.name ASC"
		);
		$stmt->execute( [ $postId ] );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Tag::fromArray( $row ), $rows );
	}

	/**
	 * Load author for a post
	 *
	 * @param int $authorId
	 * @return User|null
	 */
	private function loadAuthor( int $authorId ): ?User
	{
		try
		{
			$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE id = ? LIMIT 1" );
			$stmt->execute( [ $authorId ] );
			$row = $stmt->fetch();

			if( !$row )
			{
				return null;
			}

			return User::fromArray( $row );
		}
		catch( \PDOException $e )
		{
			// Users table may not exist in test environments
			return null;
		}
	}
}
