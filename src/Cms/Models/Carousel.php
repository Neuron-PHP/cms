<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Enums\CarouselDisplay;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, HasMany};
use Neuron\Orm\DependentStrategy;

/**
 * Named carousel ( Hero, Partners, etc. ) rendered by the [carousel] shortcode.
 *
 * @package Neuron\Cms\Models
 */
#[Table('carousels')]
class Carousel extends Model
{
	private ?int $_id = null;
	private string $_name = '';
	private string $_slug = '';
	private ?string $_description = null;
	private string $_display = CarouselDisplay::SLIDER->value;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	/** @var CarouselSlide[] */
	#[HasMany(CarouselSlide::class, foreignKey: 'carousel_id', dependent: DependentStrategy::DeleteAll)]
	private array $_slides = [];

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

	public function getName(): string
	{
		return $this->_name;
	}

	public function setName( string $name ): self
	{
		$this->_name = $name;
		return $this;
	}

	public function getSlug(): string
	{
		return $this->_slug;
	}

	public function setSlug( string $slug ): self
	{
		$this->_slug = $slug;
		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->_description;
	}

	public function setDescription( ?string $description ): self
	{
		$this->_description = $description;
		return $this;
	}

	public function getDisplay(): string
	{
		return $this->_display;
	}

	public function getDisplayMode(): CarouselDisplay
	{
		return CarouselDisplay::fromValue( $this->_display );
	}

	public function setDisplay( string $display ): self
	{
		$this->_display = CarouselDisplay::fromValue( $display )->value;
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

	/**
	 * @return CarouselSlide[]
	 */
	public function getSlides(): array
	{
		return $this->_slides;
	}

	/**
	 * @param CarouselSlide[] $slides
	 */
	public function setSlides( array $slides ): self
	{
		$this->_slides = $slides;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$carousel = new self();

		if( isset( $data['id'] ) )
		{
			$carousel->setId( (int) $data['id'] );
		}

		$carousel->setName( $data['name'] ?? '' );
		$carousel->setSlug( $data['slug'] ?? '' );
		$carousel->setDescription( $data['description'] ?? null );
		$carousel->setDisplay( $data['display'] ?? CarouselDisplay::SLIDER->value );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$carousel->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$carousel->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $carousel;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'name' => $this->_name,
			'slug' => $this->_slug,
			'description' => $this->_description,
			'display' => $this->_display,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
