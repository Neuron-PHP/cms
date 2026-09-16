<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\Table;

/**
 * Admin-managed HTTP redirect (301 or 302).
 *
 * @package Neuron\Cms\Models
 */
#[Table('redirects')]
class Redirect extends Model
{
	public const STATUS_PERMANENT = 301;
	public const STATUS_TEMPORARY = 302;

	private ?int $_id = null;
	private string $_fromPath = '';
	private string $_toUrl = '';
	private int $_statusCode = self::STATUS_PERMANENT;
	private bool $_isActive = true;
	private bool $_preserveQuery = true;
	private ?string $_notes = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

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

	public function getFromPath(): string
	{
		return $this->_fromPath;
	}

	public function setFromPath( string $fromPath ): self
	{
		$this->_fromPath = $fromPath;
		return $this;
	}

	public function getToUrl(): string
	{
		return $this->_toUrl;
	}

	public function setToUrl( string $toUrl ): self
	{
		$this->_toUrl = $toUrl;
		return $this;
	}

	public function getStatusCode(): int
	{
		return $this->_statusCode;
	}

	public function setStatusCode( int $statusCode ): self
	{
		$this->_statusCode = $statusCode;
		return $this;
	}

	public function isActive(): bool
	{
		return $this->_isActive;
	}

	public function setIsActive( bool $isActive ): self
	{
		$this->_isActive = $isActive;
		return $this;
	}

	public function getPreserveQuery(): bool
	{
		return $this->_preserveQuery;
	}

	public function setPreserveQuery( bool $preserveQuery ): self
	{
		$this->_preserveQuery = $preserveQuery;
		return $this;
	}

	public function getNotes(): ?string
	{
		return $this->_notes;
	}

	public function setNotes( ?string $notes ): self
	{
		$this->_notes = $notes;
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
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$redirect = new self();

		if( isset( $data['id'] ) )
		{
			$redirect->setId( (int) $data['id'] );
		}

		$redirect->setFromPath( $data['from_path'] ?? '' );
		$redirect->setToUrl( $data['to_url'] ?? '' );
		$redirect->setStatusCode( (int) ( $data['status_code'] ?? self::STATUS_PERMANENT ) );
		$redirect->setIsActive( (bool) ( $data['is_active'] ?? true ) );
		$redirect->setPreserveQuery( (bool) ( $data['preserve_query'] ?? true ) );
		$redirect->setNotes( $data['notes'] ?? null );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$redirect->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$redirect->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $redirect;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'from_path' => $this->_fromPath,
			'to_url' => $this->_toUrl,
			'status_code' => $this->_statusCode,
			'is_active' => $this->_isActive,
			'preserve_query' => $this->_preserveQuery,
			'notes' => $this->_notes,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
