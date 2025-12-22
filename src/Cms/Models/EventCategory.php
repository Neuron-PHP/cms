<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsToMany};

/**
 * EventCategory entity representing a calendar event category.
 *
 * @package Neuron\Cms\Models
 */
#[Table('event_categories')]
class EventCategory extends Model
{
	private ?int $_id = null;
	private string $_name;
	private string $_slug;
	private string $_color = '#3b82f6';
	private ?string $_description = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships
	#[BelongsToMany(Event::class, pivotTable: 'events', foreignKey: 'category_id')]
	private array $_events = [];

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
	 * Get color
	 */
	public function getColor(): string
	{
		return $this->_color;
	}

	/**
	 * Set color (hex code)
	 */
	public function setColor( string $color ): self
	{
		$this->_color = $color;
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
	 * Get events in this category
	 *
	 * @return Event[]
	 */
	public function getEvents(): array
	{
		return $this->_events;
	}

	/**
	 * Set events
	 *
	 * @param Event[] $events
	 */
	public function setEvents( array $events ): self
	{
		$this->_events = $events;
		return $this;
	}

	/**
	 * Create EventCategory from array data
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
		$category->setColor( $data['color'] ?? '#3b82f6' );
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
		return [
			'id' => $this->_id,
			'name' => $this->_name,
			'slug' => $this->_slug,
			'color' => $this->_color,
			'description' => $this->_description,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
