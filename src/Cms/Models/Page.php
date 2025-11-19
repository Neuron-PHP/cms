<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * Page entity representing a CMS page with Editor.js content.
 *
 * @package Neuron\Cms\Models
 */
#[Table('pages')]
class Page extends Model
{
	private ?int $_id = null;
	private string $_title;
	private string $_slug;
	private string $_contentRaw = '{"blocks":[]}';  // JSON string
	private string $_template = 'default';
	private ?string $_metaTitle = null;
	private ?string $_metaDescription = null;
	private ?string $_metaKeywords = null;
	private int $_authorId;
	private string $_status = 'draft';
	private ?DateTimeImmutable $_publishedAt = null;
	private int $_viewCount = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships - managed by ORM
	#[BelongsTo(User::class, foreignKey: 'author_id')]
	private ?User $_author = null;

	/**
	 * Page status constants
	 */
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';

	/**
	 * Template constants
	 */
	public const TEMPLATE_DEFAULT = 'default';
	public const TEMPLATE_FULL_WIDTH = 'full-width';
	public const TEMPLATE_SIDEBAR = 'sidebar';
	public const TEMPLATE_LANDING = 'landing';

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get page ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set page ID
	 */
	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	/**
	 * Get title
	 */
	public function getTitle(): string
	{
		return $this->_title;
	}

	/**
	 * Set title
	 */
	public function setTitle( string $title ): self
	{
		$this->_title = $title;
		return $this;
	}

	/**
	 * Get slug
	 */
	public function getSlug(): string
	{
		return $this->_slug;
	}

	/**
	 * Set slug
	 */
	public function setSlug( string $slug ): self
	{
		$this->_slug = $slug;
		return $this;
	}

	/**
	 * Get content as array (decoded Editor.js JSON)
	 */
	public function getContent(): array
	{
		return json_decode( $this->_contentRaw, true ) ?? ['blocks' => []];
	}

	/**
	 * Get raw content JSON string
	 */
	public function getContentRaw(): string
	{
		return $this->_contentRaw;
	}

	/**
	 * Set content from Editor.js JSON string
	 */
	public function setContent( string $jsonContent ): self
	{
		$this->_contentRaw = $jsonContent;
		return $this;
	}

	/**
	 * Set content from array (will be JSON encoded)
	 */
	public function setContentArray( array $content ): self
	{
		$this->_contentRaw = json_encode( $content );
		return $this;
	}

	/**
	 * Get template
	 */
	public function getTemplate(): string
	{
		return $this->_template;
	}

	/**
	 * Set template
	 */
	public function setTemplate( string $template ): self
	{
		$this->_template = $template;
		return $this;
	}

	/**
	 * Get meta title (for SEO)
	 */
	public function getMetaTitle(): ?string
	{
		return $this->_metaTitle;
	}

	/**
	 * Set meta title
	 */
	public function setMetaTitle( ?string $metaTitle ): self
	{
		$this->_metaTitle = $metaTitle;
		return $this;
	}

	/**
	 * Get meta description (for SEO)
	 */
	public function getMetaDescription(): ?string
	{
		return $this->_metaDescription;
	}

	/**
	 * Set meta description
	 */
	public function setMetaDescription( ?string $metaDescription ): self
	{
		$this->_metaDescription = $metaDescription;
		return $this;
	}

	/**
	 * Get meta keywords (for SEO)
	 */
	public function getMetaKeywords(): ?string
	{
		return $this->_metaKeywords;
	}

	/**
	 * Set meta keywords
	 */
	public function setMetaKeywords( ?string $metaKeywords ): self
	{
		$this->_metaKeywords = $metaKeywords;
		return $this;
	}

	/**
	 * Get author ID
	 */
	public function getAuthorId(): int
	{
		return $this->_authorId;
	}

	/**
	 * Set author ID
	 */
	public function setAuthorId( int $authorId ): self
	{
		$this->_authorId = $authorId;
		return $this;
	}

	/**
	 * Get author
	 */
	public function getAuthor(): ?User
	{
		return $this->_author;
	}

	/**
	 * Set author
	 */
	public function setAuthor( ?User $author ): self
	{
		$this->_author = $author;
		if( $author && $author->getId() )
		{
			$this->_authorId = $author->getId();
		}
		return $this;
	}

	/**
	 * Get status
	 */
	public function getStatus(): string
	{
		return $this->_status;
	}

	/**
	 * Set status
	 */
	public function setStatus( string $status ): self
	{
		$this->_status = $status;
		return $this;
	}

	/**
	 * Check if page is published
	 */
	public function isPublished(): bool
	{
		return $this->_status === self::STATUS_PUBLISHED;
	}

	/**
	 * Check if page is draft
	 */
	public function isDraft(): bool
	{
		return $this->_status === self::STATUS_DRAFT;
	}

	/**
	 * Get published date
	 */
	public function getPublishedAt(): ?DateTimeImmutable
	{
		return $this->_publishedAt;
	}

	/**
	 * Set published date
	 */
	public function setPublishedAt( ?DateTimeImmutable $publishedAt ): self
	{
		$this->_publishedAt = $publishedAt;
		return $this;
	}

	/**
	 * Get view count
	 */
	public function getViewCount(): int
	{
		return $this->_viewCount;
	}

	/**
	 * Set view count
	 */
	public function setViewCount( int $viewCount ): self
	{
		$this->_viewCount = $viewCount;
		return $this;
	}

	/**
	 * Increment view count
	 */
	public function incrementViewCount(): self
	{
		$this->_viewCount++;
		return $this;
	}

	/**
	 * Get created timestamp
	 */
	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_createdAt;
	}

	/**
	 * Set created timestamp
	 */
	public function setCreatedAt( DateTimeImmutable $createdAt ): self
	{
		$this->_createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get updated timestamp
	 */
	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_updatedAt;
	}

	/**
	 * Set updated timestamp
	 */
	public function setUpdatedAt( ?DateTimeImmutable $updatedAt ): self
	{
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	/**
	 * Create Page from array data
	 *
	 * @param array $data Associative array of page data
	 * @return static
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$page = new self();

		if( isset( $data['id'] ) )
		{
			$page->setId( (int)$data['id'] );
		}

		$page->setTitle( $data['title'] ?? '' );
		$page->setSlug( $data['slug'] ?? '' );

		// Handle content (could be JSON string or array)
		if( isset( $data['content'] ) )
		{
			if( is_string( $data['content'] ) )
			{
				$page->setContent( $data['content'] );
			}
			elseif( is_array( $data['content'] ) )
			{
				$page->setContentArray( $data['content'] );
			}
		}

		$page->setTemplate( $data['template'] ?? self::TEMPLATE_DEFAULT );
		$page->setMetaTitle( $data['meta_title'] ?? null );
		$page->setMetaDescription( $data['meta_description'] ?? null );
		$page->setMetaKeywords( $data['meta_keywords'] ?? null );
		$page->setAuthorId( (int)($data['author_id'] ?? 0) );
		$page->setStatus( $data['status'] ?? self::STATUS_DRAFT );
		$page->setViewCount( (int)($data['view_count'] ?? 0) );

		if( isset( $data['published_at'] ) && $data['published_at'] )
		{
			$page->setPublishedAt(
				is_string( $data['published_at'] )
					? new DateTimeImmutable( $data['published_at'] )
					: $data['published_at']
			);
		}

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$page->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$page->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		// Relationships
		if( isset( $data['author'] ) && $data['author'] instanceof User )
		{
			$page->setAuthor( $data['author'] );
		}

		return $page;
	}

	/**
	 * Convert page to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'title' => $this->_title,
			'slug' => $this->_slug,
			'content' => $this->_contentRaw,
			'template' => $this->_template,
			'meta_title' => $this->_metaTitle,
			'meta_description' => $this->_metaDescription,
			'meta_keywords' => $this->_metaKeywords,
			'author_id' => $this->_authorId,
			'status' => $this->_status,
			'published_at' => $this->_publishedAt?->format( 'Y-m-d H:i:s' ),
			'view_count' => $this->_viewCount,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
