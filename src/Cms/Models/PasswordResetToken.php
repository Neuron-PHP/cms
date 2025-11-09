<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;

/**
 * Password reset token entity.
 *
 * Represents a token for password reset requests with expiration.
 *
 * @package Neuron\Cms\Models
 */
class PasswordResetToken
{
	private ?int $_Id = null;
	private string $_Email;
	private string $_Token;
	private DateTimeImmutable $_CreatedAt;
	private DateTimeImmutable $_ExpiresAt;

	/**
	 * Create a new password reset token
	 *
	 * @param string $Email User's email address
	 * @param string $Token Hashed token
	 * @param int $ExpirationMinutes Token expiration in minutes (default: 60)
	 */
	public function __construct( string $Email = '', string $Token = '', int $ExpirationMinutes = 60 )
	{
		$this->_Email = $Email;
		$this->_Token = $Token;
		$this->_CreatedAt = new DateTimeImmutable();
		$this->_ExpiresAt = $this->_CreatedAt->modify( "+{$ExpirationMinutes} minutes" );
	}

	/**
	 * Get token ID
	 */
	public function getId(): ?int
	{
		return $this->_Id;
	}

	/**
	 * Set token ID
	 */
	public function setId( int $Id ): self
	{
		$this->_Id = $Id;
		return $this;
	}

	/**
	 * Get email address
	 */
	public function getEmail(): string
	{
		return $this->_Email;
	}

	/**
	 * Set email address
	 */
	public function setEmail( string $Email ): self
	{
		$this->_Email = $Email;
		return $this;
	}

	/**
	 * Get token (hashed)
	 */
	public function getToken(): string
	{
		return $this->_Token;
	}

	/**
	 * Set token (should be hashed before setting)
	 */
	public function setToken( string $Token ): self
	{
		$this->_Token = $Token;
		return $this;
	}

	/**
	 * Get created timestamp
	 */
	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->_CreatedAt;
	}

	/**
	 * Set created timestamp
	 */
	public function setCreatedAt( DateTimeImmutable $CreatedAt ): self
	{
		$this->_CreatedAt = $CreatedAt;
		return $this;
	}

	/**
	 * Get expiration timestamp
	 */
	public function getExpiresAt(): DateTimeImmutable
	{
		return $this->_ExpiresAt;
	}

	/**
	 * Set expiration timestamp
	 */
	public function setExpiresAt( DateTimeImmutable $ExpiresAt ): self
	{
		$this->_ExpiresAt = $ExpiresAt;
		return $this;
	}

	/**
	 * Check if token has expired
	 */
	public function isExpired(): bool
	{
		return new DateTimeImmutable() > $this->_ExpiresAt;
	}

	/**
	 * Create token from array data
	 */
	public static function fromArray( array $Data ): self
	{
		$Token = new self();

		if( isset( $Data['id'] ) )
		{
			$Token->setId( (int) $Data['id'] );
		}

		if( isset( $Data['email'] ) )
		{
			$Token->setEmail( $Data['email'] );
		}

		if( isset( $Data['token'] ) )
		{
			$Token->setToken( $Data['token'] );
		}

		if( isset( $Data['created_at'] ) )
		{
			$Token->setCreatedAt( new DateTimeImmutable( $Data['created_at'] ) );
		}

		if( isset( $Data['expires_at'] ) )
		{
			$Token->setExpiresAt( new DateTimeImmutable( $Data['expires_at'] ) );
		}

		return $Token;
	}

	/**
	 * Convert token to array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_Id,
			'email' => $this->_Email,
			'token' => $this->_Token,
			'created_at' => $this->_CreatedAt->format( 'Y-m-d H:i:s' ),
			'expires_at' => $this->_ExpiresAt->format( 'Y-m-d H:i:s' )
		];
	}
}
