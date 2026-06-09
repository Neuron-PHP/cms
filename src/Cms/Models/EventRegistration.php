<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * EventRegistration entity representing a visitor/member registration for an event.
 *
 * @package Neuron\Cms\Models
 */
#[Table('event_registrations')]
class EventRegistration extends Model
{
	private ?int $_id = null;
	private int $_eventId;
	private ?int $_userId = null;
	private string $_name;
	private string $_email;
	private ?string $_notes = null;
	private string $_status = 'registered';
	private ?string $_ipAddress = null;
	private ?string $_userAgent = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Event::class, foreignKey: 'event_id')]
	private ?Event $_event = null;

	#[BelongsTo(User::class, foreignKey: 'user_id')]
	private ?User $_user = null;

	/**
	 * Registration status constants
	 */
	public const STATUS_REGISTERED = 'registered';
	public const STATUS_CANCELLED = 'cancelled';

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get registration ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set registration ID
	 */
	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	/**
	 * Get event ID
	 */
	public function getEventId(): int
	{
		return $this->_eventId;
	}

	/**
	 * Set event ID
	 */
	public function setEventId( int $eventId ): self
	{
		$this->_eventId = $eventId;
		return $this;
	}

	/**
	 * Get user ID (null for anonymous registrations)
	 */
	public function getUserId(): ?int
	{
		return $this->_userId;
	}

	/**
	 * Set user ID
	 */
	public function setUserId( ?int $userId ): self
	{
		$this->_userId = $userId;
		return $this;
	}

	/**
	 * Get registrant name
	 */
	public function getName(): string
	{
		return $this->_name;
	}

	/**
	 * Set registrant name
	 */
	public function setName( string $name ): self
	{
		$this->_name = $name;
		return $this;
	}

	/**
	 * Get registrant email
	 */
	public function getEmail(): string
	{
		return $this->_email;
	}

	/**
	 * Set registrant email
	 */
	public function setEmail( string $email ): self
	{
		$this->_email = $email;
		return $this;
	}

	/**
	 * Get notes
	 */
	public function getNotes(): ?string
	{
		return $this->_notes;
	}

	/**
	 * Set notes
	 */
	public function setNotes( ?string $notes ): self
	{
		$this->_notes = $notes;
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
	 * Get IP address
	 */
	public function getIpAddress(): ?string
	{
		return $this->_ipAddress;
	}

	/**
	 * Set IP address
	 */
	public function setIpAddress( ?string $ipAddress ): self
	{
		$this->_ipAddress = $ipAddress;
		return $this;
	}

	/**
	 * Get user agent
	 */
	public function getUserAgent(): ?string
	{
		return $this->_userAgent;
	}

	/**
	 * Set user agent
	 */
	public function setUserAgent( ?string $userAgent ): self
	{
		$this->_userAgent = $userAgent;
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
	 * Get the related event
	 */
	public function getEvent(): ?Event
	{
		return $this->_event;
	}

	/**
	 * Set the related event
	 */
	public function setEvent( ?Event $event ): self
	{
		$this->_event = $event;

		if( $event && $event->getId() )
		{
			$this->_eventId = $event->getId();
		}

		return $this;
	}

	/**
	 * Get the related user
	 */
	public function getUser(): ?User
	{
		return $this->_user;
	}

	/**
	 * Set the related user
	 */
	public function setUser( ?User $user ): self
	{
		$this->_user = $user;

		if( $user && $user->getId() )
		{
			$this->_userId = $user->getId();
		}

		return $this;
	}

	/**
	 * Create EventRegistration from array data
	 *
	 * @param array $data Associative array of registration data
	 * @return static
	 */
	public static function fromArray( array $data ): static
	{
		$registration = new self();

		if( isset( $data['id'] ) )
		{
			$registration->setId( (int)$data['id'] );
		}

		$registration->setEventId( (int)( $data['event_id'] ?? 0 ) );
		$registration->setUserId( isset( $data['user_id'] ) && $data['user_id'] !== null ? (int)$data['user_id'] : null );
		$registration->setName( $data['name'] ?? '' );
		$registration->setEmail( $data['email'] ?? '' );
		$registration->setNotes( $data['notes'] ?? null );
		$registration->setStatus( $data['status'] ?? self::STATUS_REGISTERED );
		$registration->setIpAddress( $data['ip_address'] ?? null );
		$registration->setUserAgent( $data['user_agent'] ?? null );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$registration->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$registration->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $registration;
	}

	/**
	 * Convert registration to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'event_id' => $this->_eventId,
			'user_id' => $this->_userId,
			'name' => $this->_name,
			'email' => $this->_email,
			'notes' => $this->_notes,
			'status' => $this->_status,
			'ip_address' => $this->_ipAddress,
			'user_agent' => $this->_userAgent,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];

		if( $this->_id !== null )
		{
			$data['id'] = $this->_id;
		}

		return $data;
	}
}
