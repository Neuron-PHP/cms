<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * A quote in a named testimonial collection.
 *
 * @package Neuron\Cms\Models
 */
#[Table('testimonial_quotes')]
class TestimonialQuote extends Model
{
	private ?int $_id = null;
	private int $_testimonialId = 0;
	private string $_quote = '';
	private ?string $_attribution = null;
	private ?string $_role = null;
	private ?string $_organization = null;
	private ?string $_imageUrl = null;
	private int $_sortOrder = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Testimonial::class, foreignKey: 'testimonial_id')]
	private ?Testimonial $_testimonial = null;

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

	public function getTestimonialId(): int
	{
		return $this->_testimonialId;
	}

	public function setTestimonialId( int $testimonialId ): self
	{
		$this->_testimonialId = $testimonialId;
		return $this;
	}

	public function getQuote(): string
	{
		return $this->_quote;
	}

	public function setQuote( string $quote ): self
	{
		$this->_quote = $quote;
		return $this;
	}

	public function getAttribution(): ?string
	{
		return $this->_attribution;
	}

	public function setAttribution( ?string $attribution ): self
	{
		$this->_attribution = $attribution;
		return $this;
	}

	public function getRole(): ?string
	{
		return $this->_role;
	}

	public function setRole( ?string $role ): self
	{
		$this->_role = $role;
		return $this;
	}

	public function getOrganization(): ?string
	{
		return $this->_organization;
	}

	public function setOrganization( ?string $organization ): self
	{
		$this->_organization = $organization;
		return $this;
	}

	public function getImageUrl(): ?string
	{
		return $this->_imageUrl;
	}

	public function setImageUrl( ?string $imageUrl ): self
	{
		$this->_imageUrl = $imageUrl;
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

	public function getTestimonial(): ?Testimonial
	{
		return $this->_testimonial;
	}

	public function setTestimonial( ?Testimonial $testimonial ): self
	{
		$this->_testimonial = $testimonial;
		return $this;
	}

	public function citation(): string
	{
		$parts = array_values( array_filter( [
			$this->_attribution,
			$this->_role,
			$this->_organization,
		], fn( $value ) => is_string( $value ) && trim( $value ) !== '' ) );

		return implode( ', ', $parts );
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$quote = new self();

		if( isset( $data['id'] ) )
		{
			$quote->setId( (int) $data['id'] );
		}

		$quote->setTestimonialId( (int) ( $data['testimonial_id'] ?? 0 ) );
		$quote->setQuote( $data['quote'] ?? '' );
		$quote->setAttribution( $data['attribution'] ?? null );
		$quote->setRole( $data['role'] ?? null );
		$quote->setOrganization( $data['organization'] ?? null );
		$quote->setImageUrl( $data['image_url'] ?? null );
		$quote->setSortOrder( (int) ( $data['sort_order'] ?? 0 ) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$quote->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$quote->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $quote;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'testimonial_id' => $this->_testimonialId,
			'quote' => $this->_quote,
			'attribution' => $this->_attribution,
			'role' => $this->_role,
			'organization' => $this->_organization,
			'image_url' => $this->_imageUrl,
			'sort_order' => $this->_sortOrder,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
