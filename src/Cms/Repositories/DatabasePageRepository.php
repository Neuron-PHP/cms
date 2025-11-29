<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\User;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed page repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabasePageRepository implements IPageRepository
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
	 * Find page by ID
	 */
	public function findById( int $id ): ?Page
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM pages WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $id ] );

		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return $this->mapRowToPage( $row );
	}

	/**
	 * Find page by slug
	 */
	public function findBySlug( string $slug ): ?Page
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM pages WHERE slug = ? LIMIT 1" );
		$stmt->execute( [ $slug ] );

		$row = $stmt->fetch();

		if( !$row )
		{
			return null;
		}

		return $this->mapRowToPage( $row );
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO pages (
				title, slug, content, template, meta_title, meta_description,
				meta_keywords, author_id, status, published_at, view_count,
				created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$page->getTitle(),
			$page->getSlug(),
			$page->getContentRaw(),
			$page->getTemplate(),
			$page->getMetaTitle(),
			$page->getMetaDescription(),
			$page->getMetaKeywords(),
			$page->getAuthorId(),
			$page->getStatus(),
			$page->getPublishedAt() ? $page->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$page->getViewCount(),
			$page->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$page->setId( (int)$this->_pdo->lastInsertId() );

		return $page;
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

		$stmt = $this->_pdo->prepare(
			"UPDATE pages SET
				title = ?,
				slug = ?,
				content = ?,
				template = ?,
				meta_title = ?,
				meta_description = ?,
				meta_keywords = ?,
				author_id = ?,
				status = ?,
				published_at = ?,
				view_count = ?,
				updated_at = ?
			WHERE id = ?"
		);

		$result = $stmt->execute([
			$page->getTitle(),
			$page->getSlug(),
			$page->getContentRaw(),
			$page->getTemplate(),
			$page->getMetaTitle(),
			$page->getMetaDescription(),
			$page->getMetaKeywords(),
			$page->getAuthorId(),
			$page->getStatus(),
			$page->getPublishedAt() ? $page->getPublishedAt()->format( 'Y-m-d H:i:s' ) : null,
			$page->getViewCount(),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$page->getId()
		]);

		return $result;
	}

	/**
	 * Delete a page
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM pages WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all pages
	 */
	public function all( ?string $status = null, int $limit = 0, int $offset = 0 ): array
	{
		$sql = "SELECT * FROM pages";
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

		return array_map( [ $this, 'mapRowToPage' ], $rows );
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
		$sql = "SELECT * FROM pages WHERE author_id = ?";
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

		return array_map( [ $this, 'mapRowToPage' ], $rows );
	}

	/**
	 * Count total pages
	 */
	public function count( ?string $status = null ): int
	{
		$sql = "SELECT COUNT(*) as total FROM pages";
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
	 * Increment page view count
	 */
	public function incrementViewCount( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( "UPDATE pages SET view_count = view_count + 1 WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Map database row to Page object
	 *
	 * @param array $row Database row
	 * @return Page
	 */
	private function mapRowToPage( array $row ): Page
	{
		$data = [
			'id' => (int)$row['id'],
			'title' => $row['title'],
			'slug' => $row['slug'],
			'content' => $row['content'],
			'template' => $row['template'],
			'meta_title' => $row['meta_title'],
			'meta_description' => $row['meta_description'],
			'meta_keywords' => $row['meta_keywords'],
			'author_id' => (int)$row['author_id'],
			'status' => $row['status'],
			'view_count' => (int)$row['view_count'],
			'published_at' => $row['published_at'] ?? null,
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'] ?? null,
		];

		$page = Page::fromArray( $data );

		// Load relationships
		$page->setAuthor( $this->loadAuthor( $page->getAuthorId() ) );

		return $page;
	}

	/**
	 * Load author for a page
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
