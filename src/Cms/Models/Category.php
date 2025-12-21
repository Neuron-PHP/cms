<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsToMany};

/**
 * Category entity representing a blog post category.
 *
 * @package Neuron\Cms\Models
 */
#[Table('categories')]
class Category extends Model
{
	private ?int $_id = null;
	private string $_name;
	private string $_slug;
	private ?string $_description = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships
	#[BelongsToMany(Post::class, pivotTable: 'post_categories')]
	private array $_posts = [];

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get category ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set category ID
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
	 * Get description
	 */
	public function getDescription(): ?string
	{
		return $this->_description;
	}

	/**
	 * Set description
	 */
	public function setDescription( ?string $description ): self
	{
		$this->_description = $description;
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
	 * Create Category from array data
	 *
	 * @param array $data Associative array of category data
	 * @return static
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$category = new self();

		if( isset( $data['id'] ) )
		{
			$category->setId( (int)$data['id'] );
		}

		$category->setName( $data['name'] ?? '' );
		$category->setSlug( $data['slug'] ?? '' );
		$category->setDescription( $data['description'] ?? null );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$category->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$category->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $category;
	}

	/**
	 * Convert category to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'name' => $this->_name,
			'slug' => $this->_slug,
			'description' => $this->_description,
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
