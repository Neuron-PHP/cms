<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;

/**
 * "Remember me" token entity.
 *
 * One per browser that signed in with "remember me". Only the SHA-256 hash of
 * the token is stored; the browser holds the plain value in its cookie.
 *
 * @package Neuron\Cms\Models
 */
class RememberToken
{
	private ?int $_id = null;
	private int $_userId;
	private string $_token;
	private DateTimeImmutable $_createdAt;
	private ?DateTimeImmutable $_lastUsedAt = null;
	private DateTimeImmutable $_expiresAt;

	/**
	 * Create a new remember token
	 *
	 * @param int $userId User the token signs in
	 * @param string $token Hashed token
	 * @param int $expirationMinutes Token expiration in minutes (default: 30 days)
	 */
	public function __construct( int $userId = 0, string $token = '', int $expirationMinutes = 43200 )
	{
		$this->_userId = $userId;
		$this->_token = $token;
		$this->_createdAt = new DateTimeImmutable();
		$this->_expiresAt = $this->_createdAt->modify( "+{$expirationMinutes} minutes" );
	}

	/**
	 * Get token ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set token ID
	 */
	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	/**
	 * Get user ID
	 */
	public function getUserId(): int
	{
		return $this->_userId;
	}

	/**
	 * Set user ID
	 */
	public function setUserId( int $userId ): self
	{
		$this->_userId = $userId;
		return $this;
	}

	/**
	 * Get token (hashed)
	 */
	public function getToken(): string
	{
		return $this->_token;
	}

	/**
	 * Set token (should be hashed before setting)
	 */
	public function setToken( string $token ): self
	{
		$this->_token = $token;
		return $this;
	}

	/**
	 * Get created timestamp
	 */
	public function getCreatedAt(): DateTimeImmutable
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
	 * Get the time the token last signed the user in, if it has
	 */
	public function getLastUsedAt(): ?DateTimeImmutable
	{
		return $this->_lastUsedAt;
	}

	/**
	 * Set last used timestamp
	 */
	public function setLastUsedAt( ?DateTimeImmutable $lastUsedAt ): self
	{
		$this->_lastUsedAt = $lastUsedAt;
		return $this;
	}

	/**
	 * Get expiration timestamp
	 */
	public function getExpiresAt(): DateTimeImmutable
	{
		return $this->_expiresAt;
	}

	/**
	 * Set expiration timestamp
	 */
	public function setExpiresAt( DateTimeImmutable $expiresAt ): self
	{
		$this->_expiresAt = $expiresAt;
		return $this;
	}

	/**
	 * Check if token has expired
	 */
	public function isExpired(): bool
	{
		return new DateTimeImmutable() > $this->_expiresAt;
	}

	/**
	 * Create token from array data
	 */
	public static function fromArray( array $data ): self
	{
		$token = new self();

		if( isset( $data['id'] ) )
		{
			$token->setId( (int) $data['id'] );
		}

		if( isset( $data['user_id'] ) )
		{
			$token->setUserId( (int) $data['user_id'] );
		}

		if( isset( $data['token'] ) )
		{
			$token->setToken( $data['token'] );
		}

		if( isset( $data['created_at'] ) )
		{
			$token->setCreatedAt( new DateTimeImmutable( $data['created_at'] ) );
		}

		if( isset( $data['last_used_at'] ) )
		{
			$token->setLastUsedAt( new DateTimeImmutable( $data['last_used_at'] ) );
		}

		if( isset( $data['expires_at'] ) )
		{
			$token->setExpiresAt( new DateTimeImmutable( $data['expires_at'] ) );
		}

		return $token;
	}

	/**
	 * Convert token to array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'user_id' => $this->_userId,
			'token' => $this->_token,
			'created_at' => $this->_createdAt->format( 'Y-m-d H:i:s' ),
			'last_used_at' => $this->_lastUsedAt?->format( 'Y-m-d H:i:s' ),
			'expires_at' => $this->_expiresAt->format( 'Y-m-d H:i:s' )
		];
	}
}
