<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;

/**
 * Post entity representing a blog post.
 *
 * @package Neuron\Cms\Models
 */
class Post
{
	private ?int $_Id = null;
	private string $_Title;
	private string $_Slug;
	private string $_Body;
	private ?string $_Excerpt = null;
	private ?string $_FeaturedImage = null;
	private int $_AuthorId;
	private string $_Status = 'draft';
	private ?DateTimeImmutable $_PublishedAt = null;
	private int $_ViewCount = 0;
	private ?DateTimeImmutable $_CreatedAt = null;
	private ?DateTimeImmutable $_UpdatedAt = null;

	// Relationships - these will be populated by the repository
	private ?User $_Author = null;
	private array $_Categories = [];
	private array $_Tags = [];

	/**
	 * Post status constants
	 */
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_SCHEDULED = 'scheduled';

	public function __construct()
	{
		$this->_CreatedAt = new DateTimeImmutable();
	}

	/**
	 * Get post ID
	 */
	public function getId(): ?int
	{
		return $this->_Id;
	}

	/**
	 * Set post ID
	 */
	public function setId( int $Id ): self
	{
		$this->_Id = $Id;
		return $this;
	}

	/**
	 * Get title
	 */
	public function getTitle(): string
	{
		return $this->_Title;
	}

	/**
	 * Set title
	 */
	public function setTitle( string $Title ): self
	{
		$this->_Title = $Title;
		return $this;
	}

	/**
	 * Get slug
	 */
	public function getSlug(): string
	{
		return $this->_Slug;
	}

	/**
	 * Set slug
	 */
	public function setSlug( string $Slug ): self
	{
		$this->_Slug = $Slug;
		return $this;
	}

	/**
	 * Get body content
	 */
	public function getBody(): string
	{
		return $this->_Body;
	}

	/**
	 * Set body content
	 */
	public function setBody( string $Body ): self
	{
		$this->_Body = $Body;
		return $this;
	}

	/**
	 * Get excerpt
	 */
	public function getExcerpt(): ?string
	{
		return $this->_Excerpt;
	}

	/**
	 * Set excerpt
	 */
	public function setExcerpt( ?string $Excerpt ): self
	{
		$this->_Excerpt = $Excerpt;
		return $this;
	}

	/**
	 * Get featured image
	 */
	public function getFeaturedImage(): ?string
	{
		return $this->_FeaturedImage;
	}

	/**
	 * Set featured image
	 */
	public function setFeaturedImage( ?string $FeaturedImage ): self
	{
		$this->_FeaturedImage = $FeaturedImage;
		return $this;
	}

	/**
	 * Get author ID
	 */
	public function getAuthorId(): int
	{
		return $this->_AuthorId;
	}

	/**
	 * Set author ID
	 */
	public function setAuthorId( int $AuthorId ): self
	{
		$this->_AuthorId = $AuthorId;
		return $this;
	}

	/**
	 * Get author
	 */
	public function getAuthor(): ?User
	{
		return $this->_Author;
	}

	/**
	 * Set author
	 */
	public function setAuthor( ?User $Author ): self
	{
		$this->_Author = $Author;
		if( $Author && $Author->getId() )
		{
			$this->_AuthorId = $Author->getId();
		}
		return $this;
	}

	/**
	 * Get status
	 */
	public function getStatus(): string
	{
		return $this->_Status;
	}

	/**
	 * Set status
	 */
	public function setStatus( string $Status ): self
	{
		$this->_Status = $Status;
		return $this;
	}

	/**
	 * Check if post is published
	 */
	public function isPublished(): bool
	{
		return $this->_Status === self::STATUS_PUBLISHED;
	}

	/**
	 * Check if post is draft
	 */
	public function isDraft(): bool
	{
		return $this->_Status === self::STATUS_DRAFT;
	}

	/**
	 * Check if post is scheduled
	 */
	public function isScheduled(): bool
	{
		return $this->_Status === self::STATUS_SCHEDULED;
	}

	/**
	 * Get published date
	 */
	public function getPublishedAt(): ?DateTimeImmutable
	{
		return $this->_PublishedAt;
	}

	/**
	 * Set published date
	 */
	public function setPublishedAt( ?DateTimeImmutable $PublishedAt ): self
	{
		$this->_PublishedAt = $PublishedAt;
		return $this;
	}

	/**
	 * Get view count
	 */
	public function getViewCount(): int
	{
		return $this->_ViewCount;
	}

	/**
	 * Set view count
	 */
	public function setViewCount( int $ViewCount ): self
	{
		$this->_ViewCount = $ViewCount;
		return $this;
	}

	/**
	 * Increment view count
	 */
	public function incrementViewCount(): self
	{
		$this->_ViewCount++;
		return $this;
	}

	/**
	 * Get created timestamp
	 */
	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_CreatedAt;
	}

	/**
	 * Set created timestamp
	 */
	public function setCreatedAt( DateTimeImmutable $CreatedAt ): self
	{
		$this->_CreatedAt = $CreatedAt;
		return $this;
	}

	/**
	 * Get updated timestamp
	 */
	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_UpdatedAt;
	}

	/**
	 * Set updated timestamp
	 */
	public function setUpdatedAt( ?DateTimeImmutable $UpdatedAt ): self
	{
		$this->_UpdatedAt = $UpdatedAt;
		return $this;
	}

	/**
	 * Get categories
	 *
	 * @return Category[]
	 */
	public function getCategories(): array
	{
		return $this->_Categories;
	}

	/**
	 * Set categories
	 *
	 * @param Category[] $Categories
	 */
	public function setCategories( array $Categories ): self
	{
		$this->_Categories = $Categories;
		return $this;
	}

	/**
	 * Add category
	 */
	public function addCategory( Category $Category ): self
	{
		if( !$this->hasCategory( $Category ) )
		{
			$this->_Categories[] = $Category;
		}
		return $this;
	}

	/**
	 * Remove category
	 */
	public function removeCategory( Category $Category ): self
	{
		$this->_Categories = array_filter(
			$this->_Categories,
			fn( $c ) => $c->getId() !== $Category->getId()
		);
		return $this;
	}

	/**
	 * Check if post has category
	 */
	public function hasCategory( Category $Category ): bool
	{
		foreach( $this->_Categories as $c )
		{
			if( $c->getId() === $Category->getId() )
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Get tags
	 *
	 * @return Tag[]
	 */
	public function getTags(): array
	{
		return $this->_Tags;
	}

	/**
	 * Set tags
	 *
	 * @param Tag[] $Tags
	 */
	public function setTags( array $Tags ): self
	{
		$this->_Tags = $Tags;
		return $this;
	}

	/**
	 * Add tag
	 */
	public function addTag( Tag $Tag ): self
	{
		if( !$this->hasTag( $Tag ) )
		{
			$this->_Tags[] = $Tag;
		}
		return $this;
	}

	/**
	 * Remove tag
	 */
	public function removeTag( Tag $Tag ): self
	{
		$this->_Tags = array_filter(
			$this->_Tags,
			fn( $t ) => $t->getId() !== $Tag->getId()
		);
		return $this;
	}

	/**
	 * Check if post has tag
	 */
	public function hasTag( Tag $Tag ): bool
	{
		foreach( $this->_Tags as $t )
		{
			if( $t->getId() === $Tag->getId() )
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Create Post from array data
	 *
	 * @param array $Data Associative array of post data
	 * @return Post
	 * @throws Exception
	 */
	public static function fromArray( array $Data ): Post
	{
		$Post = new self();

		if( isset( $Data['id'] ) )
		{
			$Post->setId( (int)$Data['id'] );
		}

		$Post->setTitle( $Data['title'] ?? '' );
		$Post->setSlug( $Data['slug'] ?? '' );
		$Post->setBody( $Data['body'] ?? '' );
		$Post->setExcerpt( $Data['excerpt'] ?? null );
		$Post->setFeaturedImage( $Data['featured_image'] ?? null );
		$Post->setAuthorId( (int)($Data['author_id'] ?? 0) );
		$Post->setStatus( $Data['status'] ?? self::STATUS_DRAFT );
		$Post->setViewCount( (int)($Data['view_count'] ?? 0) );

		if( isset( $Data['published_at'] ) && $Data['published_at'] )
		{
			$Post->setPublishedAt(
				is_string( $Data['published_at'] )
					? new DateTimeImmutable( $Data['published_at'] )
					: $Data['published_at']
			);
		}

		if( isset( $Data['created_at'] ) && $Data['created_at'] )
		{
			$Post->setCreatedAt(
				is_string( $Data['created_at'] )
					? new DateTimeImmutable( $Data['created_at'] )
					: $Data['created_at']
			);
		}

		if( isset( $Data['updated_at'] ) && $Data['updated_at'] )
		{
			$Post->setUpdatedAt(
				is_string( $Data['updated_at'] )
					? new DateTimeImmutable( $Data['updated_at'] )
					: $Data['updated_at']
			);
		}

		// Relationships
		if( isset( $Data['author'] ) && $Data['author'] instanceof User )
		{
			$Post->setAuthor( $Data['author'] );
		}

		if( isset( $Data['categories'] ) && is_array( $Data['categories'] ) )
		{
			$Post->setCategories( $Data['categories'] );
		}

		if( isset( $Data['tags'] ) && is_array( $Data['tags'] ) )
		{
			$Post->setTags( $Data['tags'] );
		}

		return $Post;
	}

	/**
	 * Convert post to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_Id,
			'title' => $this->_Title,
			'slug' => $this->_Slug,
			'body' => $this->_Body,
			'excerpt' => $this->_Excerpt,
			'featured_image' => $this->_FeaturedImage,
			'author_id' => $this->_AuthorId,
			'status' => $this->_Status,
			'published_at' => $this->_PublishedAt?->format( 'Y-m-d H:i:s' ),
			'view_count' => $this->_ViewCount,
			'created_at' => $this->_CreatedAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_UpdatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
