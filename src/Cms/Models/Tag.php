<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsToMany};
use Neuron\Orm\DependentStrategy;

/**
 * Tag entity representing a blog post tag.
 *
 * @package Neuron\Cms\Models
 */
#[Table('tags')]
class Tag extends Model
{
	private ?int $_id = null;
	private string $_name;
	private string $_slug;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships
	// DeleteAll: When tag is deleted, remove pivot entries (keep posts)
	#[BelongsToMany(Post::class, pivotTable: 'post_tags', dependent: DependentStrategy::DeleteAll)]
	private array $_posts = [];

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get tag ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set tag ID
	 */
	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	/**
	 * Get name
	 */
	public function getName(): string
	{
		return $this->_name;
	}

	/**
	 * Set name
	 */
	public function setName( string $name ): self
	{
		$this->_name = $name;
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
	 * Create Tag from array data
	 *
	 * @param array $data Associative array of tag data
	 * @return static
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$tag = new self();

		if( isset( $data['id'] ) )
		{
			$tag->setId( (int)$data['id'] );
		}

		$tag->setName( $data['name'] ?? '' );
		$tag->setSlug( $data['slug'] ?? '' );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$tag->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$tag->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $tag;
	}

	/**
	 * Convert tag to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'name' => $this->_name,
			'slug' => $this->_slug,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];

		// Only include id if it's set (not null) to avoid PostgreSQL NOT NULL constraint errors
		if( $this->_id !== null )
		{
			$data['id'] = $this->_id;
		}

		return $data;
	}
}
