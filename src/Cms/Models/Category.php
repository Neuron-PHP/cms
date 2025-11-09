<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;

/**
 * Category entity representing a blog post category.
 *
 * @package Neuron\Cms\Models
 */
class Category
{
	private ?int $_Id = null;
	private string $_Name;
	private string $_Slug;
	private ?string $_Description = null;
	private ?DateTimeImmutable $_CreatedAt = null;
	private ?DateTimeImmutable $_UpdatedAt = null;

	public function __construct()
	{
		$this->_CreatedAt = new DateTimeImmutable();
	}

	/**
	 * Get category ID
	 */
	public function getId(): ?int
	{
		return $this->_Id;
	}

	/**
	 * Set category ID
	 */
	public function setId( int $Id ): self
	{
		$this->_Id = $Id;
		return $this;
	}

	/**
	 * Get name
	 */
	public function getName(): string
	{
		return $this->_Name;
	}

	/**
	 * Set name
	 */
	public function setName( string $Name ): self
	{
		$this->_Name = $Name;
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
	 * Get description
	 */
	public function getDescription(): ?string
	{
		return $this->_Description;
	}

	/**
	 * Set description
	 */
	public function setDescription( ?string $Description ): self
	{
		$this->_Description = $Description;
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
	 * Create Category from array data
	 *
	 * @param array $Data Associative array of category data
	 * @return Category
	 * @throws Exception
	 */
	public static function fromArray( array $Data ): Category
	{
		$Category = new self();

		if( isset( $Data['id'] ) )
		{
			$Category->setId( (int)$Data['id'] );
		}

		$Category->setName( $Data['name'] ?? '' );
		$Category->setSlug( $Data['slug'] ?? '' );
		$Category->setDescription( $Data['description'] ?? null );

		if( isset( $Data['created_at'] ) && $Data['created_at'] )
		{
			$Category->setCreatedAt(
				is_string( $Data['created_at'] )
					? new DateTimeImmutable( $Data['created_at'] )
					: $Data['created_at']
			);
		}

		if( isset( $Data['updated_at'] ) && $Data['updated_at'] )
		{
			$Category->setUpdatedAt(
				is_string( $Data['updated_at'] )
					? new DateTimeImmutable( $Data['updated_at'] )
					: $Data['updated_at']
			);
		}

		return $Category;
	}

	/**
	 * Convert category to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_Id,
			'name' => $this->_Name,
			'slug' => $this->_Slug,
			'description' => $this->_Description,
			'created_at' => $this->_CreatedAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_UpdatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
