<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\User;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
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
	 * Find post by ID
	 */
	public function findById( int $Id ): ?Post
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM posts WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $Id ] );

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
	public function findBySlug( string $Slug ): ?Post
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM posts WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $Slug ] );

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
	public function create( Post $Post ): Post
	{
		// Check for duplicate slug
		if( $this->findBySlug( $Post->getSlug() ) )
		{
			throw new Exception( 'Slug already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO posts (
				title, slug, body, excerpt, featured_image, author_id,
				status, published_at, view_count, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$Post->getTitle(),
			$Post->getSlug(),
			$Post->getBody(),
			$Post->getExcerpt(),
			$Post->getFeaturedImage(),
			$Post->getAuthorId(),
			$Post->getStatus(),
			$Post->getPublishedAt() ? $Post->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$Post->getViewCount(),
			$Post->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$Post->setId( (int)$this->_PDO->lastInsertId() );

		// Handle categories
		if( count( $Post->getCategories() ) > 0 )
		{
			$categoryIds = array_map( fn( $c ) => $c->getId(), $Post->getCategories() );
			$this->attachCategories( $Post->getId(), $categoryIds );
		}

		// Handle tags
		if( count( $Post->getTags() ) > 0 )
		{
			$tagIds = array_map( fn( $t ) => $t->getId(), $Post->getTags() );
			$this->attachTags( $Post->getId(), $tagIds );
		}

		return $Post;
	}

	/**
	 * Update an existing post
	 */
	public function update( Post $Post ): bool
	{
		if( !$Post->getId() )
		{
			return false;
		}

		// Check for duplicate slug (excluding current post)
		$ExistingBySlug = $this->findBySlug( $Post->getSlug() );
		if( $ExistingBySlug && $ExistingBySlug->getId() !== $Post->getId() )
		{
			throw new Exception( 'Slug already exists' );
		}

		$stmt = $this->_PDO->prepare(
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
			$Post->getTitle(),
			$Post->getSlug(),
			$Post->getBody(),
			$Post->getExcerpt(),
			$Post->getFeaturedImage(),
			$Post->getAuthorId(),
			$Post->getStatus(),
			$Post->getPublishedAt() ? $Post->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$Post->getViewCount(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$Post->getId()
		]);

		// Update categories
		$this->detachCategories( $Post->getId() );
		if( count( $Post->getCategories() ) > 0 )
		{
			$categoryIds = array_map( fn( $c ) => $c->getId(), $Post->getCategories() );
			$this->attachCategories( $Post->getId(), $categoryIds );
		}

		// Update tags
		$this->detachTags( $Post->getId() );
		if( count( $Post->getTags() ) > 0 )
		{
			$tagIds = array_map( fn( $t ) => $t->getId(), $Post->getTags() );
			$this->attachTags( $Post->getId(), $tagIds );
		}

		return $result;
	}

	/**
	 * Delete a post
	 */
	public function delete( int $Id ): bool
	{
		// Foreign key constraints will handle cascade delete of relationships
		$stmt = $this->_PDO->prepare( "DELETE FROM posts WHERE id = ?" );
		$stmt->execute( [ $Id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all posts
	 */
	public function all( ?string $Status = null, int $Limit = 0, int $Offset = 0 ): array
	{
		$sql = "SELECT * FROM posts";
		$params = [];

		if( $Status )
		{
			$sql .= " WHERE status = ?";
			$params[] = $Status;
		}

		$sql .= " ORDER BY created_at DESC";

		if( $Limit > 0 )
		{
			$sql .= " LIMIT ? OFFSET ?";
			$params[] = $Limit;
			$params[] = $Offset;
		}

		$stmt = $this->_PDO->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by author
	 */
	public function getByAuthor( int $AuthorId, ?string $Status = null ): array
	{
		$sql = "SELECT * FROM posts WHERE author_id = ?";
		$params = [ $AuthorId ];

		if( $Status )
		{
			$sql .= " AND status = ?";
			$params[] = $Status;
		}

		$sql .= " ORDER BY created_at DESC";

		$stmt = $this->_PDO->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by category
	 */
	public function getByCategory( int $CategoryId, ?string $Status = null ): array
	{
		$sql = "SELECT p.* FROM posts p
				INNER JOIN post_categories pc ON p.id = pc.post_id
				WHERE pc.category_id = ?";
		$params = [ $CategoryId ];

		if( $Status )
		{
			$sql .= " AND p.status = ?";
			$params[] = $Status;
		}

		$sql .= " ORDER BY p.created_at DESC";

		$stmt = $this->_PDO->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get posts by tag
	 */
	public function getByTag( int $TagId, ?string $Status = null ): array
	{
		$sql = "SELECT p.* FROM posts p
				INNER JOIN post_tags pt ON p.id = pt.post_id
				WHERE pt.tag_id = ?";
		$params = [ $TagId ];

		if( $Status )
		{
			$sql .= " AND p.status = ?";
			$params[] = $Status;
		}

		$sql .= " ORDER BY p.created_at DESC";

		$stmt = $this->_PDO->prepare( $sql );
		$stmt->execute( $params );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToPost' ], $rows );
	}

	/**
	 * Get published posts
	 */
	public function getPublished( int $Limit = 0, int $Offset = 0 ): array
	{
		return $this->all( Post::STATUS_PUBLISHED, $Limit, $Offset );
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
	public function count( ?string $Status = null ): int
	{
		$sql = "SELECT COUNT(*) as total FROM posts";
		$params = [];

		if( $Status )
		{
			$sql .= " WHERE status = ?";
			$params[] = $Status;
		}

		$stmt = $this->_PDO->prepare( $sql );
		$stmt->execute( $params );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Increment post view count
	 */
	public function incrementViewCount( int $Id ): bool
	{
		$stmt = $this->_PDO->prepare( "UPDATE posts SET view_count = view_count + 1 WHERE id = ?" );
		$stmt->execute( [ $Id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Attach categories to post
	 */
	public function attachCategories( int $PostId, array $CategoryIds ): bool
	{
		if( empty( $CategoryIds ) )
		{
			return true;
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)"
		);

		foreach( $CategoryIds as $categoryId )
		{
			$stmt->execute([
				$PostId,
				$categoryId,
				(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
			]);
		}

		return true;
	}

	/**
	 * Detach all categories from post
	 */
	public function detachCategories( int $PostId ): bool
	{
		$stmt = $this->_PDO->prepare( "DELETE FROM post_categories WHERE post_id = ?" );
		$stmt->execute( [ $PostId ] );

		return true;
	}

	/**
	 * Attach tags to post
	 */
	public function attachTags( int $PostId, array $TagIds ): bool
	{
		if( empty( $TagIds ) )
		{
			return true;
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)"
		);

		foreach( $TagIds as $tagId )
		{
			$stmt->execute([
				$PostId,
				$tagId,
				(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
			]);
		}

		return true;
	}

	/**
	 * Detach all tags from post
	 */
	public function detachTags( int $PostId ): bool
	{
		$stmt = $this->_PDO->prepare( "DELETE FROM post_tags WHERE post_id = ?" );
		$stmt->execute( [ $PostId ] );

		return true;
	}

	/**
	 * Map database row to Post object
	 *
	 * @param array $Row Database row
	 * @return Post
	 */
	private function mapRowToPost( array $Row ): Post
	{
		$data = [
			'id' => (int)$Row['id'],
			'title' => $Row['title'],
			'slug' => $Row['slug'],
			'body' => $Row['body'],
			'excerpt' => $Row['excerpt'],
			'featured_image' => $Row['featured_image'],
			'author_id' => (int)$Row['author_id'],
			'status' => $Row['status'],
			'view_count' => (int)$Row['view_count'],
			'published_at' => $Row['published_at'] ?? null,
			'created_at' => $Row['created_at'],
			'updated_at' => $Row['updated_at'] ?? null,
		];

		$Post = Post::fromArray( $data );

		// Load relationships
		$Post->setCategories( $this->loadCategories( $Post->getId() ) );
		$Post->setTags( $this->loadTags( $Post->getId() ) );

		return $Post;
	}

	/**
	 * Load categories for a post
	 *
	 * @param int $PostId
	 * @return Category[]
	 */
	private function loadCategories( int $PostId ): array
	{
		$stmt = $this->_PDO->prepare(
			"SELECT c.* FROM categories c
			INNER JOIN post_categories pc ON c.id = pc.category_id
			WHERE pc.post_id = ?
			ORDER BY c.name ASC"
		);
		$stmt->execute( [ $PostId ] );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Category::fromArray( $row ), $rows );
	}

	/**
	 * Load tags for a post
	 *
	 * @param int $PostId
	 * @return Tag[]
	 */
	private function loadTags( int $PostId ): array
	{
		$stmt = $this->_PDO->prepare(
			"SELECT t.* FROM tags t
			INNER JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.post_id = ?
			ORDER BY t.name ASC"
		);
		$stmt->execute( [ $PostId ] );
		$rows = $stmt->fetchAll();

		return array_map( fn( $row ) => Tag::fromArray( $row ), $rows );
	}
}
