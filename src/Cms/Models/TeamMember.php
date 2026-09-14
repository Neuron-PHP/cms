<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * A person on a named team. Photo comes from the media library as a URL.
 *
 * @package Neuron\Cms\Models
 */
#[Table('team_members')]
class TeamMember extends Model
{
	private ?int $_id = null;
	private int $_teamId = 0;
	private string $_name = '';
	private ?string $_title = null;
	private ?string $_bio = null;
	private ?string $_contact = null;
	private ?string $_imageUrl = null;
	private int $_sortOrder = 0;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Team::class, foreignKey: 'team_id')]
	private ?Team $_team = null;

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

	public function getTeamId(): int
	{
		return $this->_teamId;
	}

	public function setTeamId( int $teamId ): self
	{
		$this->_teamId = $teamId;
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

	public function getTitle(): ?string
	{
		return $this->_title;
	}

	public function setTitle( ?string $title ): self
	{
		$this->_title = $title;
		return $this;
	}

	public function getBio(): ?string
	{
		return $this->_bio;
	}

	public function setBio( ?string $bio ): self
	{
		$this->_bio = $bio;
		return $this;
	}

	public function getContact(): ?string
	{
		return $this->_contact;
	}

	public function setContact( ?string $contact ): self
	{
		$this->_contact = $contact;
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

	public function getTeam(): ?Team
	{
		return $this->_team;
	}

	public function setTeam( ?Team $team ): self
	{
		$this->_team = $team;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$member = new self();

		if( isset( $data['id'] ) )
		{
			$member->setId( (int) $data['id'] );
		}

		$member->setTeamId( (int) ( $data['team_id'] ?? 0 ) );
		$member->setName( $data['name'] ?? '' );
		$member->setTitle( $data['title'] ?? null );
		$member->setBio( $data['bio'] ?? null );
		$member->setContact( $data['contact'] ?? null );
		$member->setImageUrl( $data['image_url'] ?? null );
		$member->setSortOrder( (int) ( $data['sort_order'] ?? 0 ) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$member->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$member->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $member;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'team_id' => $this->_teamId,
			'name' => $this->_name,
			'title' => $this->_title,
			'bio' => $this->_bio,
			'contact' => $this->_contact,
			'image_url' => $this->_imageUrl,
			'sort_order' => $this->_sortOrder,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
