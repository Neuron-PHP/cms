<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;

/**
 * User entity representing a CMS user.
 *
 * @package Neuron\Cms\Models
 */
class User
{
	private ?int $_Id = null;
	private string $_Username;
	private string $_Email;
	private string $_PasswordHash;
	private string $_Role = 'subscriber';
	private string $_Status = 'active';
	private bool $_EmailVerified = false;
	private ?string $_RememberToken = null;
	private ?string $_TwoFactorSecret = null;
	private ?array $_TwoFactorRecoveryCodes = null;
	private int $_FailedLoginAttempts = 0;
	private ?DateTimeImmutable $_LockedUntil = null;
	private ?DateTimeImmutable $_CreatedAt = null;
	private ?DateTimeImmutable $_UpdatedAt = null;
	private ?DateTimeImmutable $_LastLoginAt = null;

	/**
	 * User roles
	 */
	public const ROLE_ADMIN = 'admin';
	public const ROLE_EDITOR = 'editor';
	public const ROLE_AUTHOR = 'author';
	public const ROLE_SUBSCRIBER = 'subscriber';

	/**
	 * User statuses
	 */
	public const STATUS_ACTIVE = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_SUSPENDED = 'suspended';

	public function __construct()
	{
		$this->_CreatedAt = new DateTimeImmutable();
	}

	/**
	 * Get user ID
	 */
	public function getId(): ?int
	{
		return $this->_Id;
	}

	/**
	 * Set user ID
	 */
	public function setId( int $Id ): self
	{
		$this->_Id = $Id;
		return $this;
	}

	/**
	 * Get username
	 */
	public function getUsername(): string
	{
		return $this->_Username;
	}

	/**
	 * Set username
	 */
	public function setUsername( string $Username ): self
	{
		$this->_Username = $Username;
		return $this;
	}

	/**
	 * Get email
	 */
	public function getEmail(): string
	{
		return $this->_Email;
	}

	/**
	 * Set email
	 */
	public function setEmail( string $Email ): self
	{
		$this->_Email = $Email;
		return $this;
	}

	/**
	 * Get password hash
	 */
	public function getPasswordHash(): string
	{
		return $this->_PasswordHash;
	}

	/**
	 * Set password hash
	 */
	public function setPasswordHash( string $PasswordHash ): self
	{
		$this->_PasswordHash = $PasswordHash;
		return $this;
	}

	/**
	 * Get user role
	 */
	public function getRole(): string
	{
		return $this->_Role;
	}

	/**
	 * Set user role
	 */
	public function setRole( string $Role ): self
	{
		$this->_Role = $Role;
		return $this;
	}

	/**
	 * Check if user is admin
	 */
	public function isAdmin(): bool
	{
		return $this->_Role === self::ROLE_ADMIN;
	}

	/**
	 * Check if user is editor
	 */
	public function isEditor(): bool
	{
		return $this->_Role === self::ROLE_EDITOR;
	}

	/**
	 * Check if user is author
	 */
	public function isAuthor(): bool
	{
		return $this->_Role === self::ROLE_AUTHOR;
	}

	/**
	 * Get user status
	 */
	public function getStatus(): string
	{
		return $this->_Status;
	}

	/**
	 * Set user status
	 */
	public function setStatus( string $Status ): self
	{
		$this->_Status = $Status;
		return $this;
	}

	/**
	 * Check if user is active
	 */
	public function isActive(): bool
	{
		return $this->_Status === self::STATUS_ACTIVE;
	}

	/**
	 * Check if user is suspended
	 */
	public function isSuspended(): bool
	{
		return $this->_Status === self::STATUS_SUSPENDED;
	}

	/**
	 * Check if email is verified
	 */
	public function isEmailVerified(): bool
	{
		return $this->_EmailVerified;
	}

	/**
	 * Set email verified status
	 */
	public function setEmailVerified( bool $EmailVerified ): self
	{
		$this->_EmailVerified = $EmailVerified;
		return $this;
	}

	/**
	 * Get remember token
	 */
	public function getRememberToken(): ?string
	{
		return $this->_RememberToken;
	}

	/**
	 * Set remember token
	 */
	public function setRememberToken( ?string $RememberToken ): self
	{
		$this->_RememberToken = $RememberToken;
		return $this;
	}

	/**
	 * Get two-factor secret
	 */
	public function getTwoFactorSecret(): ?string
	{
		return $this->_TwoFactorSecret;
	}

	/**
	 * Set two-factor secret
	 */
	public function setTwoFactorSecret( ?string $TwoFactorSecret ): self
	{
		$this->_TwoFactorSecret = $TwoFactorSecret;
		return $this;
	}

	/**
	 * Check if two-factor authentication is enabled
	 */
	public function hasTwoFactorEnabled(): bool
	{
		return $this->_TwoFactorSecret !== null;
	}

	/**
	 * Get two-factor recovery codes
	 */
	public function getTwoFactorRecoveryCodes(): ?array
	{
		return $this->_TwoFactorRecoveryCodes;
	}

	/**
	 * Set two-factor recovery codes
	 */
	public function setTwoFactorRecoveryCodes( ?array $TwoFactorRecoveryCodes ): self
	{
		$this->_TwoFactorRecoveryCodes = $TwoFactorRecoveryCodes;
		return $this;
	}

	/**
	 * Get failed login attempts count
	 */
	public function getFailedLoginAttempts(): int
	{
		return $this->_FailedLoginAttempts;
	}

	/**
	 * Set failed login attempts count
	 */
	public function setFailedLoginAttempts( int $FailedLoginAttempts ): self
	{
		$this->_FailedLoginAttempts = $FailedLoginAttempts;
		return $this;
	}

	/**
	 * Increment failed login attempts
	 */
	public function incrementFailedLoginAttempts(): self
	{
		$this->_FailedLoginAttempts++;
		return $this;
	}

	/**
	 * Reset failed login attempts
	 */
	public function resetFailedLoginAttempts(): self
	{
		$this->_FailedLoginAttempts = 0;
		$this->_LockedUntil = null;
		return $this;
	}

	/**
	 * Get locked until timestamp
	 */
	public function getLockedUntil(): ?DateTimeImmutable
	{
		return $this->_LockedUntil;
	}

	/**
	 * Set locked until timestamp
	 */
	public function setLockedUntil( ?DateTimeImmutable $LockedUntil ): self
	{
		$this->_LockedUntil = $LockedUntil;
		return $this;
	}

	/**
	 * Check if user is locked out
	 */
	public function isLockedOut(): bool
	{
		if( $this->_LockedUntil === null )
		{
			return false;
		}

		return $this->_LockedUntil > new DateTimeImmutable();
	}

	/**
	 * Get created at timestamp
	 */
	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_CreatedAt;
	}

	/**
	 * Set created at timestamp
	 */
	public function setCreatedAt( DateTimeImmutable $CreatedAt ): self
	{
		$this->_CreatedAt = $CreatedAt;
		return $this;
	}

	/**
	 * Get updated at timestamp
	 */
	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_UpdatedAt;
	}

	/**
	 * Set updated at timestamp
	 */
	public function setUpdatedAt( ?DateTimeImmutable $UpdatedAt ): self
	{
		$this->_UpdatedAt = $UpdatedAt;
		return $this;
	}

	/**
	 * Get last login at timestamp
	 */
	public function getLastLoginAt(): ?DateTimeImmutable
	{
		return $this->_LastLoginAt;
	}

	/**
	 * Set last login at timestamp
	 */
	public function setLastLoginAt( ?DateTimeImmutable $LastLoginAt ): self
	{
		$this->_LastLoginAt = $LastLoginAt;
		return $this;
	}

	/**
	 * Convert user to array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_Id,
			'username' => $this->_Username,
			'email' => $this->_Email,
			'password_hash' => $this->_PasswordHash,
			'role' => $this->_Role,
			'status' => $this->_Status,
			'email_verified' => $this->_EmailVerified,
			'remember_token' => $this->_RememberToken,
			'two_factor_secret' => $this->_TwoFactorSecret,
			'two_factor_recovery_codes' => $this->_TwoFactorRecoveryCodes ? json_encode( $this->_TwoFactorRecoveryCodes ) : null,
			'failed_login_attempts' => $this->_FailedLoginAttempts,
			'locked_until' => $this->_LockedUntil?->format( 'Y-m-d H:i:s' ),
			'created_at' => $this->_CreatedAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_UpdatedAt?->format( 'Y-m-d H:i:s' ),
			'last_login_at' => $this->_LastLoginAt?->format( 'Y-m-d H:i:s' )
		];
	}

	/**
	 * Create user from array
	 */
	public static function fromArray( array $Data ): self
	{
		$User = new self();

		if( isset( $Data['id'] ) )
		{
			$User->setId( $Data['id'] );
		}

		$User->setUsername( $Data['username'] );
		$User->setEmail( $Data['email'] );
		$User->setPasswordHash( $Data['password_hash'] );
		$User->setRole( $Data['role'] ?? self::ROLE_SUBSCRIBER );
		$User->setStatus( $Data['status'] ?? self::STATUS_ACTIVE );
		$User->setEmailVerified( $Data['email_verified'] ?? false );
		$User->setRememberToken( $Data['remember_token'] ?? null );
		$User->setTwoFactorSecret( $Data['two_factor_secret'] ?? null );

		if( isset( $Data['two_factor_recovery_codes'] ) && $Data['two_factor_recovery_codes'] )
		{
			$User->setTwoFactorRecoveryCodes( json_decode( $Data['two_factor_recovery_codes'], true ) );
		}

		$User->setFailedLoginAttempts( $Data['failed_login_attempts'] ?? 0 );

		if( isset( $Data['locked_until'] ) && $Data['locked_until'] )
		{
			$User->setLockedUntil( new DateTimeImmutable( $Data['locked_until'] ) );
		}

		if( isset( $Data['created_at'] ) && $Data['created_at'] )
		{
			$User->setCreatedAt( new DateTimeImmutable( $Data['created_at'] ) );
		}

		if( isset( $Data['updated_at'] ) && $Data['updated_at'] )
		{
			$User->setUpdatedAt( new DateTimeImmutable( $Data['updated_at'] ) );
		}

		if( isset( $Data['last_login_at'] ) && $Data['last_login_at'] )
		{
			$User->setLastLoginAt( new DateTimeImmutable( $Data['last_login_at'] ) );
		}

		return $User;
	}
}
