<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Enums\TestimonialDisplay;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, HasMany};
use Neuron\Orm\DependentStrategy;

/**
 * Named testimonial collection rendered by the [testimonial] shortcode.
 *
 * @package Neuron\Cms\Models
 */
#[Table('testimonials')]
class Testimonial extends Model
{
	private ?int $_id = null;
	private string $_name = '';
	private string $_slug = '';
	private ?string $_description = null;
	private string $_display = TestimonialDisplay::CARDS->value;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	/** @var TestimonialQuote[] */
	#[HasMany(TestimonialQuote::class, foreignKey: 'testimonial_id', dependent: DependentStrategy::DeleteAll)]
	private array $_quotes = [];

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

	public function getDisplayMode(): TestimonialDisplay
	{
		return TestimonialDisplay::fromValue( $this->_display );
	}

	public function setDisplay( string $display ): self
	{
		$this->_display = TestimonialDisplay::fromValue( $display )->value;
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
	 * @return TestimonialQuote[]
	 */
	public function getQuotes(): array
	{
		return $this->_quotes;
	}

	/**
	 * @param TestimonialQuote[] $quotes
	 */
	public function setQuotes( array $quotes ): self
	{
		$this->_quotes = $quotes;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$testimonial = new self();

		if( isset( $data['id'] ) )
		{
			$testimonial->setId( (int) $data['id'] );
		}

		$testimonial->setName( $data['name'] ?? '' );
		$testimonial->setSlug( $data['slug'] ?? '' );
		$testimonial->setDescription( $data['description'] ?? null );
		$testimonial->setDisplay( $data['display'] ?? TestimonialDisplay::CARDS->value );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$testimonial->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$testimonial->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $testimonial;
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
