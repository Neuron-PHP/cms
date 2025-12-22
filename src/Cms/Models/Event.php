<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * Event entity representing a calendar event.
 *
 * @package Neuron\Cms\Models
 */
#[Table('events')]
class Event extends Model
{
	private ?int $_id = null;
	private string $_title;
	private string $_slug;
	private ?string $_description = null;
	private string $_contentRaw = '{"blocks":[]}';  // JSON string for Editor.js
	private ?string $_location = null;
	private DateTimeImmutable $_startDate;
	private ?DateTimeImmutable $_endDate = null;
	private bool $_allDay = false;
	private ?int $_categoryId = null;
	private string $_status = 'draft';
	private ?string $_featuredImage = null;
	private ?string $_organizer = null;
	private ?string $_contactEmail = null;
	private ?string $_contactPhone = null;
	private ?int $_createdBy = null;
	private int $_viewCount = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	// Relationships
	#[BelongsTo(User::class, foreignKey: 'created_by')]
	private ?User $_creator = null;

	#[BelongsTo(EventCategory::class, foreignKey: 'category_id')]
	private ?EventCategory $_category = null;

	/**
	 * Event status constants
	 */
	public const STATUS_DRAFT = 'draft';
	public const STATUS_PUBLISHED = 'published';

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get event ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set event ID
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
	 * @param array $content Content array to encode
	 * @return self
	 * @throws \JsonException If JSON encoding fails
	 */
	public function setContentArray( array $content ): self
	{
		$encoded = json_encode( $content );

		if( $encoded === false )
		{
			$error = json_last_error_msg();
			throw new \JsonException( "Failed to encode content array to JSON: {$error}" );
		}

		$this->_contentRaw = $encoded;
		return $this;
	}

	/**
	 * Get location
	 */
	public function getLocation(): ?string
	{
		return $this->_location;
	}

	/**
	 * Set location
	 */
	public function setLocation( ?string $location ): self
	{
		$this->_location = $location;
		return $this;
	}

	/**
	 * Get start date
	 */
	public function getStartDate(): DateTimeImmutable
	{
		return $this->_startDate;
	}

	/**
	 * Set start date
	 */
	public function setStartDate( DateTimeImmutable $startDate ): self
	{
		$this->_startDate = $startDate;
		return $this;
	}

	/**
	 * Get end date
	 */
	public function getEndDate(): ?DateTimeImmutable
	{
		return $this->_endDate;
	}

	/**
	 * Set end date
	 */
	public function setEndDate( ?DateTimeImmutable $endDate ): self
	{
		$this->_endDate = $endDate;
		return $this;
	}

	/**
	 * Check if event is all day
	 */
	public function isAllDay(): bool
	{
		return $this->_allDay;
	}

	/**
	 * Set all day flag
	 */
	public function setAllDay( bool $allDay ): self
	{
		$this->_allDay = $allDay;
		return $this;
	}

	/**
	 * Get category ID
	 */
	public function getCategoryId(): ?int
	{
		return $this->_categoryId;
	}

	/**
	 * Set category ID
	 */
	public function setCategoryId( ?int $categoryId ): self
	{
		$this->_categoryId = $categoryId;
		return $this;
	}

	/**
	 * Get category
	 */
	public function getCategory(): ?EventCategory
	{
		return $this->_category;
	}

	/**
	 * Set category
	 */
	public function setCategory( ?EventCategory $category ): self
	{
		$this->_category = $category;
		if( $category && $category->getId() )
		{
			$this->_categoryId = $category->getId();
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
	 * Check if event is published
	 */
	public function isPublished(): bool
	{
		return $this->_status === self::STATUS_PUBLISHED;
	}

	/**
	 * Check if event is draft
	 */
	public function isDraft(): bool
	{
		return $this->_status === self::STATUS_DRAFT;
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
	 * Get organizer
	 */
	public function getOrganizer(): ?string
	{
		return $this->_organizer;
	}

	/**
	 * Set organizer
	 */
	public function setOrganizer( ?string $organizer ): self
	{
		$this->_organizer = $organizer;
		return $this;
	}

	/**
	 * Get contact email
	 */
	public function getContactEmail(): ?string
	{
		return $this->_contactEmail;
	}

	/**
	 * Set contact email
	 */
	public function setContactEmail( ?string $contactEmail ): self
	{
		$this->_contactEmail = $contactEmail;
		return $this;
	}

	/**
	 * Get contact phone
	 */
	public function getContactPhone(): ?string
	{
		return $this->_contactPhone;
	}

	/**
	 * Set contact phone
	 */
	public function setContactPhone( ?string $contactPhone ): self
	{
		$this->_contactPhone = $contactPhone;
		return $this;
	}

	/**
	 * Get creator ID
	 */
	public function getCreatedBy(): ?int
	{
		return $this->_createdBy;
	}

	/**
	 * Set creator ID
	 */
	public function setCreatedBy( int $createdBy ): self
	{
		$this->_createdBy = $createdBy;
		return $this;
	}

	/**
	 * Get creator
	 */
	public function getCreator(): ?User
	{
		return $this->_creator;
	}

	/**
	 * Set creator
	 */
	public function setCreator( ?User $creator ): self
	{
		$this->_creator = $creator;

		if( $creator && $creator->getId() )
		{
			$this->_createdBy = $creator->getId();
		}
		else
		{
			$this->_createdBy = null;
		}

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
	 * Check if event is upcoming (starts in the future)
	 */
	public function isUpcoming(): bool
	{
		$now = new DateTimeImmutable();
		return $this->_startDate > $now;
	}

	/**
	 * Check if event is past (ended in the past)
	 */
	public function isPast(): bool
	{
		$now = new DateTimeImmutable();
		$endDate = $this->_endDate ?? $this->_startDate;
		return $endDate < $now;
	}

	/**
	 * Check if event is currently happening
	 */
	public function isOngoing(): bool
	{
		$now = new DateTimeImmutable();
		$endDate = $this->_endDate ?? $this->_startDate;
		return $this->_startDate <= $now && $endDate >= $now;
	}

	/**
	 * Create Event from array data
	 *
	 * @param array $data Associative array of event data
	 * @return static
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$event = new self();

		if( isset( $data['id'] ) )
		{
			$event->setId( (int)$data['id'] );
		}

		$event->setTitle( $data['title'] ?? '' );
		$event->setSlug( $data['slug'] ?? '' );
		$event->setDescription( $data['description'] ?? null );

		// Handle content_raw
		if( isset( $data['content_raw'] ) )
		{
			if( is_string( $data['content_raw'] ) )
			{
				$event->_contentRaw = $data['content_raw'];
			}
			elseif( is_array( $data['content_raw'] ) )
			{
				$encoded = json_encode( $data['content_raw'] );
				if( $encoded === false )
				{
					throw new \JsonException( "Failed to encode content_raw array to JSON" );
				}
				$event->_contentRaw = $encoded;
			}
		}

		$event->setLocation( $data['location'] ?? null );

		// Handle dates
		if( isset( $data['start_date'] ) )
		{
			$event->setStartDate(
				is_string( $data['start_date'] )
					? new DateTimeImmutable( $data['start_date'] )
					: $data['start_date']
			);
		}

		if( isset( $data['end_date'] ) && $data['end_date'] )
		{
			$event->setEndDate(
				is_string( $data['end_date'] )
					? new DateTimeImmutable( $data['end_date'] )
					: $data['end_date']
			);
		}

		$event->setAllDay( (bool)($data['all_day'] ?? false) );
		$event->setCategoryId( isset( $data['category_id'] ) ? (int)$data['category_id'] : null );
		$event->setStatus( $data['status'] ?? self::STATUS_DRAFT );
		$event->setFeaturedImage( $data['featured_image'] ?? null );
		$event->setOrganizer( $data['organizer'] ?? null );
		$event->setContactEmail( $data['contact_email'] ?? null );
		$event->setContactPhone( $data['contact_phone'] ?? null );
		$event->setCreatedBy( (int)($data['created_by'] ?? 0) );
		$event->setViewCount( (int)($data['view_count'] ?? 0) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$event->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$event->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		// Relationships
		if( isset( $data['creator'] ) && $data['creator'] instanceof User )
		{
			$event->setCreator( $data['creator'] );
		}

		if( isset( $data['category'] ) && $data['category'] instanceof EventCategory )
		{
			$event->setCategory( $data['category'] );
		}

		return $event;
	}

	/**
	 * Convert event to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'title' => $this->_title,
			'slug' => $this->_slug,
			'description' => $this->_description,
			'content_raw' => $this->_contentRaw,
			'location' => $this->_location,
			'start_date' => isset( $this->_startDate ) ? $this->_startDate->format( 'Y-m-d H:i:s' ) : null,
			'end_date' => $this->_endDate?->format( 'Y-m-d H:i:s' ),
			'all_day' => $this->_allDay,
			'category_id' => $this->_categoryId,
			'status' => $this->_status,
			'featured_image' => $this->_featuredImage,
			'organizer' => $this->_organizer,
			'contact_email' => $this->_contactEmail,
			'contact_phone' => $this->_contactPhone,
			'created_by' => $this->_createdBy,
			'view_count' => $this->_viewCount,
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
