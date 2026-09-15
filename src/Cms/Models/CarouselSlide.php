<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * A slide in a named carousel. Image comes from the media library as a URL.
 *
 * @package Neuron\Cms\Models
 */
#[Table('carousel_slides')]
class CarouselSlide extends Model
{
	private ?int $_id = null;
	private int $_carouselId = 0;
	private string $_imageUrl = '';
	private ?string $_altText = null;
	private ?string $_heading = null;
	private ?string $_caption = null;
	private ?string $_linkUrl = null;
	private ?string $_linkLabel = null;
	private int $_sortOrder = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Carousel::class, foreignKey: 'carousel_id')]
	private ?Carousel $_carousel = null;

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->_id;
	}

	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	public function getCarouselId(): int
	{
		return $this->_carouselId;
	}

	public function setCarouselId( int $carouselId ): self
	{
		$this->_carouselId = $carouselId;
		return $this;
	}

	public function getImageUrl(): string
	{
		return $this->_imageUrl;
	}

	public function setImageUrl( string $imageUrl ): self
	{
		$this->_imageUrl = $imageUrl;
		return $this;
	}

	public function getAltText(): ?string
	{
		return $this->_altText;
	}

	public function setAltText( ?string $altText ): self
	{
		$this->_altText = $altText;
		return $this;
	}

	public function getHeading(): ?string
	{
		return $this->_heading;
	}

	public function setHeading( ?string $heading ): self
	{
		$this->_heading = $heading;
		return $this;
	}

	public function getCaption(): ?string
	{
		return $this->_caption;
	}

	public function setCaption( ?string $caption ): self
	{
		$this->_caption = $caption;
		return $this;
	}

	public function getLinkUrl(): ?string
	{
		return $this->_linkUrl;
	}

	public function setLinkUrl( ?string $linkUrl ): self
	{
		$this->_linkUrl = $linkUrl;
		return $this;
	}

	public function getLinkLabel(): ?string
	{
		return $this->_linkLabel;
	}

	public function setLinkLabel( ?string $linkLabel ): self
	{
		$this->_linkLabel = $linkLabel;
		return $this;
	}

	public function getSortOrder(): int
	{
		return $this->_sortOrder;
	}

	public function setSortOrder( int $sortOrder ): self
	{
		$this->_sortOrder = $sortOrder;
		return $this;
	}

	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_createdAt;
	}

	public function setCreatedAt( DateTimeImmutable $createdAt ): self
	{
		$this->_createdAt = $createdAt;
		return $this;
	}

	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_updatedAt;
	}

	public function setUpdatedAt( ?DateTimeImmutable $updatedAt ): self
	{
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	public function getCarousel(): ?Carousel
	{
		return $this->_carousel;
	}

	public function setCarousel( ?Carousel $carousel ): self
	{
		$this->_carousel = $carousel;
		return $this;
	}

	/**
	 * Accessible alt text: explicit alt, then heading, caption, then fallback.
	 */
	public function resolveAlt( string $fallback ): string
	{
		foreach( [ $this->_altText, $this->_heading, $this->_caption, $fallback ] as $value )
		{
			if( $value !== null && trim( $value ) !== '' )
			{
				return trim( $value );
			}
		}

		return $fallback;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$slide = new self();

		if( isset( $data['id'] ) )
		{
			$slide->setId( (int) $data['id'] );
		}

		$slide->setCarouselId( (int) ( $data['carousel_id'] ?? 0 ) );
		$slide->setImageUrl( (string) ( $data['image_url'] ?? '' ) );
		$slide->setAltText( $data['alt_text'] ?? null );
		$slide->setHeading( $data['heading'] ?? null );
		$slide->setCaption( $data['caption'] ?? null );
		$slide->setLinkUrl( $data['link_url'] ?? null );
		$slide->setLinkLabel( $data['link_label'] ?? null );
		$slide->setSortOrder( (int) ( $data['sort_order'] ?? 0 ) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$slide->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$slide->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $slide;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'carousel_id' => $this->_carouselId,
			'image_url' => $this->_imageUrl,
			'alt_text' => $this->_altText,
			'heading' => $this->_heading,
			'caption' => $this->_caption,
			'link_url' => $this->_linkUrl,
			'link_label' => $this->_linkLabel,
			'sort_order' => $this->_sortOrder,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
