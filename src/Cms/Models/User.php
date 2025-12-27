<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Neuron\Cms\Enums\UserRole;
use Neuron\Cms\Enums\UserStatus;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, HasMany};

/**
 * User entity representing a CMS user.
 *
 * @package Neuron\Cms\Models
 */
#[Table('users')]
class User extends Model
{
	private ?int $_id = null;
	private string $_username;
	private string $_email;
	private string $_passwordHash;
	private string $_role = 'subscriber';
	private string $_status = 'active';
	private bool $_emailVerified = false;
	private ?string $_rememberToken = null;
	private ?string $_twoFactorSecret = null;
	private ?array $_twoFactorRecoveryCodes = null;
	private int $_failedLoginAttempts = 0;
	private ?DateTimeImmutable $_lockedUntil = null;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;
	private ?DateTimeImmutable $_lastLoginAt = null;
	private string $_timezone = 'UTC';

	// Relationships
	#[HasMany(Post::class, foreignKey: 'author_id')]
	private array $_posts = [];

	/**
	 * User roles
	 * @deprecated Use UserRole enum instead
	 */
	public const ROLE_ADMIN = 'admin';
	public const ROLE_EDITOR = 'editor';
	public const ROLE_AUTHOR = 'author';
	public const ROLE_SUBSCRIBER = 'subscriber';

	/**
	 * User statuses
	 * @deprecated Use UserStatus enum instead
	 */
	public const STATUS_ACTIVE = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_SUSPENDED = 'suspended';

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	/**
	 * Get user ID
	 */
	public function getId(): ?int
	{
		return $this->_id;
	}

	/**
	 * Set user ID
	 */
	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	/**
	 * Get username
	 */
	public function getUsername(): string
	{
		return $this->_username;
	}

	/**
	 * Set username
	 */
	public function setUsername( string $username ): self
	{
		$this->_username = $username;
		return $this;
	}

	/**
	 * Get email
	 */
	public function getEmail(): string
	{
		return $this->_email;
	}

	/**
	 * Set email
	 */
	public function setEmail( string $email ): self
	{
		$this->_email = $email;
		return $this;
	}

	/**
	 * Get password hash
	 */
	public function getPasswordHash(): string
	{
		return $this->_passwordHash;
	}

	/**
	 * Set password hash
	 */
	public function setPasswordHash( string $passwordHash ): self
	{
		$this->_passwordHash = $passwordHash;
		return $this;
	}

	/**
	 * Get user role
	 */
	public function getRole(): string
	{
		return $this->_role;
	}

	/**
	 * Set user role
	 */
	public function setRole( string $role ): self
	{
		$this->_role = $role;
		return $this;
	}

	/**
	 * Check if user is admin
	 */
	public function isAdmin(): bool
	{
		return $this->_role === self::ROLE_ADMIN;
	}

	/**
	 * Check if user is editor
	 */
	public function isEditor(): bool
	{
		return $this->_role === self::ROLE_EDITOR;
	}

	/**
	 * Check if user is author
	 */
	public function isAuthor(): bool
	{
		return $this->_role === self::ROLE_AUTHOR;
	}

	/**
	 * Get user status
	 */
	public function getStatus(): string
	{
		return $this->_status;
	}

	/**
	 * Set user status
	 */
	public function setStatus( string $status ): self
	{
		$this->_status = $status;
		return $this;
	}

	/**
	 * Check if user is active
	 */
	public function isActive(): bool
	{
		return $this->_status === self::STATUS_ACTIVE;
	}

	/**
	 * Check if user is suspended
	 */
	public function isSuspended(): bool
	{
		return $this->_status === self::STATUS_SUSPENDED;
	}

	/**
	 * Check if email is verified
	 */
	public function isEmailVerified(): bool
	{
		return $this->_emailVerified;
	}

	/**
	 * Set email verified status
	 */
	public function setEmailVerified( bool $emailVerified ): self
	{
		$this->_emailVerified = $emailVerified;
		return $this;
	}

	/**
	 * Get remember token
	 */
	public function getRememberToken(): ?string
	{
		return $this->_rememberToken;
	}

	/**
	 * Set remember token
	 */
	public function setRememberToken( ?string $rememberToken ): self
	{
		$this->_rememberToken = $rememberToken;
		return $this;
	}

	/**
	 * Get two-factor secret
	 */
	public function getTwoFactorSecret(): ?string
	{
		return $this->_twoFactorSecret;
	}

	/**
	 * Set two-factor secret
	 */
	public function setTwoFactorSecret( ?string $twoFactorSecret ): self
	{
		$this->_twoFactorSecret = $twoFactorSecret;
		return $this;
	}

	/**
	 * Check if two-factor authentication is enabled
	 */
	public function hasTwoFactorEnabled(): bool
	{
		return $this->_twoFactorSecret !== null;
	}

	/**
	 * Get two-factor recovery codes
	 */
	public function getTwoFactorRecoveryCodes(): ?array
	{
		return $this->_twoFactorRecoveryCodes;
	}

	/**
	 * Set two-factor recovery codes
	 */
	public function setTwoFactorRecoveryCodes( ?array $twoFactorRecoveryCodes ): self
	{
		$this->_twoFactorRecoveryCodes = $twoFactorRecoveryCodes;
		return $this;
	}

	/**
	 * Get failed login attempts count
	 */
	public function getFailedLoginAttempts(): int
	{
		return $this->_failedLoginAttempts;
	}

	/**
	 * Set failed login attempts count
	 */
	public function setFailedLoginAttempts( int $failedLoginAttempts ): self
	{
		$this->_failedLoginAttempts = $failedLoginAttempts;
		return $this;
	}

	/**
	 * Increment failed login attempts
	 */
	public function incrementFailedLoginAttempts(): self
	{
		$this->_failedLoginAttempts++;
		return $this;
	}

	/**
	 * Reset failed login attempts
	 */
	public function resetFailedLoginAttempts(): self
	{
		$this->_failedLoginAttempts = 0;
		$this->_lockedUntil = null;
		return $this;
	}

	/**
	 * Get locked until timestamp
	 */
	public function getLockedUntil(): ?DateTimeImmutable
	{
		return $this->_lockedUntil;
	}

	/**
	 * Set locked until timestamp
	 */
	public function setLockedUntil( ?DateTimeImmutable $lockedUntil ): self
	{
		$this->_lockedUntil = $lockedUntil;
		return $this;
	}

	/**
	 * Check if user is locked out
	 */
	public function isLockedOut(): bool
	{
		if( $this->_lockedUntil === null )
		{
			return false;
		}

		return $this->_lockedUntil > new DateTimeImmutable();
	}

	/**
	 * Get created at timestamp
	 */
	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_createdAt;
	}

	/**
	 * Set created at timestamp
	 */
	public function setCreatedAt( DateTimeImmutable $createdAt ): self
	{
		$this->_createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get updated at timestamp
	 */
	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_updatedAt;
	}

	/**
	 * Set updated at timestamp
	 */
	public function setUpdatedAt( ?DateTimeImmutable $updatedAt ): self
	{
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	/**
	 * Get last login at timestamp
	 */
	public function getLastLoginAt(): ?DateTimeImmutable
	{
		return $this->_lastLoginAt;
	}

	/**
	 * Set last login at timestamp
	 */
	public function setLastLoginAt( ?DateTimeImmutable $lastLoginAt ): self
	{
		$this->_lastLoginAt = $lastLoginAt;
		return $this;
	}

	/**
	 * Get user timezone
	 */
	public function getTimezone(): string
	{
		return $this->_timezone;
	}

	/**
	 * Set user timezone
	 */
	public function setTimezone( string $timezone ): self
	{
		$this->_timezone = $timezone;
		return $this;
	}

	/**
	 * Convert user to array
	 */
	public function toArray(): array
	{
		$data = [
			'username' => $this->_username,
			'email' => $this->_email,
			'password_hash' => $this->_passwordHash,
			'role' => $this->_role,
			'status' => $this->_status,
			'email_verified' => $this->_emailVerified,
			'remember_token' => $this->_rememberToken,
			'two_factor_secret' => $this->_twoFactorSecret,
			'two_factor_recovery_codes' => $this->_twoFactorRecoveryCodes ? json_encode( $this->_twoFactorRecoveryCodes ) : null,
			'failed_login_attempts' => $this->_failedLoginAttempts,
			'locked_until' => $this->_lockedUntil?->format( 'Y-m-d H:i:s' ),
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
			'last_login_at' => $this->_lastLoginAt?->format( 'Y-m-d H:i:s' ),
			'timezone' => $this->_timezone
		];

		// Only include id if it's set (not null) to avoid PostgreSQL NOT NULL constraint errors
		if( $this->_id !== null )
		{
			$data['id'] = $this->_id;
		}

		return $data;
	}

	/**
	 * Create user from array
	 */
	public static function fromArray( array $data ): static
	{
		$user = new self();

		if( isset( $data['id'] ) )
		{
			$user->setId( $data['id'] );
		}

		$user->setUsername( $data['username'] );
		$user->setEmail( $data['email'] );
		$user->setPasswordHash( $data['password_hash'] );
		$user->setRole( $data['role'] ?? self::ROLE_SUBSCRIBER );
		$user->setStatus( $data['status'] ?? self::STATUS_ACTIVE );
		$user->setEmailVerified( $data['email_verified'] ?? false );
		$user->setRememberToken( $data['remember_token'] ?? null );
		$user->setTwoFactorSecret( $data['two_factor_secret'] ?? null );

		if( isset( $data['two_factor_recovery_codes'] ) && $data['two_factor_recovery_codes'] )
		{
			$user->setTwoFactorRecoveryCodes( json_decode( $data['two_factor_recovery_codes'], true ) );
		}

		$user->setFailedLoginAttempts( $data['failed_login_attempts'] ?? 0 );

		if( isset( $data['locked_until'] ) && $data['locked_until'] )
		{
			$user->setLockedUntil( new DateTimeImmutable( $data['locked_until'] ) );
		}

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$user->setCreatedAt( new DateTimeImmutable( $data['created_at'] ) );
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$user->setUpdatedAt( new DateTimeImmutable( $data['updated_at'] ) );
		}

		if( isset( $data['last_login_at'] ) && $data['last_login_at'] )
		{
			$user->setLastLoginAt( new DateTimeImmutable( $data['last_login_at'] ) );
		}

		if( isset( $data['timezone'] ) && $data['timezone'] )
		{
			$user->setTimezone( $data['timezone'] );
		}

		return $user;
	}
}
