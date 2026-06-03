<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\Table;

/**
 * Content revision entity.
 *
 * Represents a single saved snapshot of a page or post at a point in time,
 * including who made the change and what kind of change it was.
 *
 * @package Neuron\Cms\Models
 */
#[Table('content_revisions')]
class Revision extends Model
{
	public const TYPE_PAGE = 'page';
	public const TYPE_POST = 'post';

	public const ACTION_CREATED  = 'created';
	public const ACTION_UPDATED  = 'updated';
	public const ACTION_RESTORED = 'restored';

	private ?int $_id = null;
	private string $_contentType = '';
	private int $_contentId = 0;
	private string $_title = '';
	private string $_status = 'draft';
	private string $_action = self::ACTION_UPDATED;
	private string $_snapshot = '{}';
	private ?int $_editedBy = null;
	private ?string $_editedByName = null;
	private ?DateTimeImmutable $_createdAt = null;

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

	public function getContentType(): string
	{
		return $this->_contentType;
	}

	public function setContentType( string $contentType ): self
	{
		$this->_contentType = $contentType;
		return $this;
	}

	public function getContentId(): int
	{
		return $this->_contentId;
	}

	public function setContentId( int $contentId ): self
	{
		$this->_contentId = $contentId;
		return $this;
	}

	public function getTitle(): string
	{
		return $this->_title;
	}

	public function setTitle( string $title ): self
	{
		$this->_title = $title;
		return $this;
	}

	public function getStatus(): string
	{
		return $this->_status;
	}

	public function setStatus( string $status ): self
	{
		$this->_status = $status;
		return $this;
	}

	public function getAction(): string
	{
		return $this->_action;
	}

	public function setAction( string $action ): self
	{
		$this->_action = $action;
		return $this;
	}

	/**
	 * Get the raw JSON snapshot string.
	 */
	public function getSnapshot(): string
	{
		return $this->_snapshot;
	}

	/**
	 * Set the snapshot from a raw JSON string.
	 */
	public function setSnapshot( string $snapshot ): self
	{
		$this->_snapshot = $snapshot;
		return $this;
	}

	/**
	 * Set the snapshot from an array (encoded to JSON).
	 *
	 * @param array $data
	 * @return self
	 * @throws \JsonException If JSON encoding fails
	 */
	public function setSnapshotArray( array $data ): self
	{
		$encoded = json_encode( $data );

		if( $encoded === false )
		{
			throw new \JsonException( 'Failed to encode revision snapshot: ' . json_last_error_msg() );
		}

		$this->_snapshot = $encoded;
		return $this;
	}

	/**
	 * Get the decoded snapshot data.
	 *
	 * @return array
	 */
	public function getSnapshotData(): array
	{
		return json_decode( $this->_snapshot, true ) ?? [];
	}

	public function getEditedBy(): ?int
	{
		return $this->_editedBy;
	}

	public function setEditedBy( ?int $editedBy ): self
	{
		$this->_editedBy = $editedBy;
		return $this;
	}

	public function getEditedByName(): ?string
	{
		return $this->_editedByName;
	}

	public function setEditedByName( ?string $editedByName ): self
	{
		$this->_editedByName = $editedByName;
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

	/**
	 * Human-friendly label for who made the change.
	 */
	public function getEditorLabel(): string
	{
		if( $this->_editedByName !== null && $this->_editedByName !== '' )
		{
			return $this->_editedByName;
		}

		return $this->_editedBy !== null ? "User #{$this->_editedBy}" : 'Unknown';
	}

	/**
	 * Create a Revision from array data.
	 *
	 * @param array $data
	 * @return static
	 */
	public static function fromArray( array $data ): static
	{
		$revision = new self();

		if( isset( $data['id'] ) )
		{
			$revision->setId( (int)$data['id'] );
		}

		$revision->setContentType( $data['content_type'] ?? '' );
		$revision->setContentId( (int)( $data['content_id'] ?? 0 ) );
		$revision->setTitle( $data['title'] ?? '' );
		$revision->setStatus( $data['status'] ?? 'draft' );
		$revision->setAction( $data['action'] ?? self::ACTION_UPDATED );
		$revision->setSnapshot( is_string( $data['snapshot'] ?? null ) ? $data['snapshot'] : '{}' );

		if( isset( $data['edited_by'] ) && $data['edited_by'] !== null )
		{
			$revision->setEditedBy( (int)$data['edited_by'] );
		}

		$revision->setEditedByName( $data['edited_by_name'] ?? null );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$revision->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		return $revision;
	}

	/**
	 * Convert the revision to an array of database columns.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'content_type'   => $this->_contentType,
			'content_id'     => $this->_contentId,
			'title'          => $this->_title,
			'status'         => $this->_status,
			'action'         => $this->_action,
			'snapshot'       => $this->_snapshot,
			'edited_by'      => $this->_editedBy,
			'edited_by_name' => $this->_editedByName,
			'created_at'     => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
		];

		// Only include id once persisted to avoid NOT NULL constraint errors.
		if( $this->_id !== null )
		{
			$data['id'] = $this->_id;
		}

		return $data;
	}
}
