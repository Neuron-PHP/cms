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
	private ?int $_id = null;
	private string $_title;
	private string $_slug;
	private string $_body;
	private ?string $_excerpt = null;
	private ?string $_featuredImage = null;
	private int $_authorId;
	private string $_status = 'draft';
	private ?DateTimeImmutable $_publishedAt = null;
	private int $_viewCount = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships - these will be populated by the repository
	private ?User $_author = null;
	private array $_categories = [];
	private array $_tags = [];

	/**
	 * Post status constants
	 */
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_SCHEDULED = 'scheduled';

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get post ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set post ID
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
	 * Get body content
	 */
	public function getBody(): string
	{
		return $this->_body;
	}

	/**
	 * Set body content
	 */
	public function setBody( string $body ): self
	{
		$this->_body = $body;
		return $this;
	}

	/**
	 * Get excerpt
	 */
	public function getExcerpt(): ?string
	{
		return $this->_excerpt;
	}

	/**
	 * Set excerpt
	 */
	public function setExcerpt( ?string $excerpt ): self
	{
		$this->_excerpt = $excerpt;
		return $this;
	}

	/**
	 * Get featured image
	 */
	public function getFeaturedImage(): ?string
	{
		return $this->_featuredImage;
	}

	/**
	 * Set featured image
	 */
	public function setFeaturedImage( ?string $featuredImage ): self
	{
		$this->_featuredImage = $featuredImage;
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
	 * Check if post is published
	 */
	public function isPublished(): bool
	{
		return $this->_status === self::STATUS_PUBLISHED;
	}

	/**
	 * Check if post is draft
	 */
	public function isDraft(): bool
	{
		return $this->_status === self::STATUS_DRAFT;
	}

	/**
	 * Check if post is scheduled
	 */
	public function isScheduled(): bool
	{
		return $this->_status === self::STATUS_SCHEDULED;
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
	 * Get categories
	 *
	 * @return Category[]
	 */
	public function getCategories(): array
	{
		return $this->_categories;
	}

	/**
	 * Set categories
	 *
	 * @param Category[] $categories
	 */
	public function setCategories( array $categories ): self
	{
		$this->_categories = $categories;
		return $this;
	}

	/**
	 * Add category
	 */
	public function addCategory( Category $category ): self
	{
		if( !$this->hasCategory( $category ) )
		{
			$this->_categories[] = $category;
		}
		return $this;
	}

	/**
	 * Remove category
	 */
	public function removeCategory( Category $category ): self
	{
		$this->_categories = array_filter(
			$this->_categories,
			fn( $c ) => $c->getId() !== $category->getId()
		);
		return $this;
	}

	/**
	 * Check if post has category
	 */
	public function hasCategory( Category $category ): bool
	{
		foreach( $this->_categories as $c )
		{
			if( $c->getId() === $category->getId() )
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
		return $this->_tags;
	}

	/**
	 * Set tags
	 *
	 * @param Tag[] $tags
	 */
	public function setTags( array $tags ): self
	{
		$this->_tags = $tags;
		return $this;
	}

	/**
	 * Add tag
	 */
	public function addTag( Tag $tag ): self
	{
		if( !$this->hasTag( $tag ) )
		{
			$this->_tags[] = $tag;
		}
		return $this;
	}

	/**
	 * Remove tag
	 */
	public function removeTag( Tag $tag ): self
	{
		$this->_tags = array_filter(
			$this->_tags,
			fn( $t ) => $t->getId() !== $tag->getId()
		);
		return $this;
	}

	/**
	 * Check if post has tag
	 */
	public function hasTag( Tag $tag ): bool
	{
		foreach( $this->_tags as $t )
		{
			if( $t->getId() === $tag->getId() )
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Create Post from array data
	 *
	 * @param array $data Associative array of post data
	 * @return Post
	 * @throws Exception
	 */
	public static function fromArray( array $data ): Post
	{
		$post = new self();

		if( isset( $data['id'] ) )
		{
			$post->setId( (int)$data['id'] );
		}

		$post->setTitle( $data['title'] ?? '' );
		$post->setSlug( $data['slug'] ?? '' );
		$post->setBody( $data['body'] ?? '' );
		$post->setExcerpt( $data['excerpt'] ?? null );
		$post->setFeaturedImage( $data['featured_image'] ?? null );
		$post->setAuthorId( (int)($data['author_id'] ?? 0) );
		$post->setStatus( $data['status'] ?? self::STATUS_DRAFT );
		$post->setViewCount( (int)($data['view_count'] ?? 0) );

		if( isset( $data['published_at'] ) && $data['published_at'] )
		{
			$post->setPublishedAt(
				is_string( $data['published_at'] )
					? new DateTimeImmutable( $data['published_at'] )
					: $data['published_at']
			);
		}

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$post->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$post->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		// Relationships
		if( isset( $data['author'] ) && $data['author'] instanceof User )
		{
			$post->setAuthor( $data['author'] );
		}

		if( isset( $data['categories'] ) && is_array( $data['categories'] ) )
		{
			$post->setCategories( $data['categories'] );
		}

		if( isset( $data['tags'] ) && is_array( $data['tags'] ) )
		{
			$post->setTags( $data['tags'] );
		}

		return $post;
	}

	/**
	 * Convert post to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'title' => $this->_title,
			'slug' => $this->_slug,
			'body' => $this->_body,
			'excerpt' => $this->_excerpt,
			'featured_image' => $this->_featuredImage,
			'author_id' => $this->_authorId,
			'status' => $this->_status,
			'published_at' => $this->_publishedAt?->format( 'Y-m-d H:i:s' ),
			'view_count' => $this->_viewCount,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
