<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\Traits\ManagesTimestamps;
use Neuron\Cms\Exceptions\DuplicateEntityException;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed user repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseUserRepository implements IUserRepository
{
	use ManagesTimestamps;

	private ?PDO $_pdo = null;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		// Keep PDO property for backwards compatibility with tests
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find user by ID
	 */
	public function findById( int $id ): ?User
	{
		return User::find( $id );
	}

	/**
	 * Find user by username
	 */
	public function findByUsername( string $username ): ?User
	{
		return User::where( 'username', $username )->first();
	}

	/**
	 * Find user by email
	 */
	public function findByEmail( string $email ): ?User
	{
		return User::where( 'email', $email )->first();
	}

	/**
	 * Find user by remember token
	 */
	public function findByRememberToken( string $token ): ?User
	{
		return User::where( 'remember_token', $token )->first();
	}

	/**
	 * Create a new user
	 */
	public function create( User $user ): User
	{
		// Check for duplicate username
		if( $this->findByUsername( $user->getUsername() ) )
		{
			throw new DuplicateEntityException( 'User', 'username', $user->getUsername() );
		}

		// Check for duplicate email
		if( $this->findByEmail( $user->getEmail() ) )
		{
			throw new DuplicateEntityException( 'User', 'email', $user->getEmail() );
		}

		// Set timestamps, save, and refresh with null-safety check
		return $this->createEntity(
			$user,
			fn( int $id ) => $this->findById( $id ),
			'User'
		);
	}

	/**
	 * Update an existing user
	 */
	public function update( User $user ): bool
	{
		if( !$user->getId() )
		{
			return false;
		}

		// Check for duplicate username (excluding current user)
		$existingByUsername = $this->findByUsername( $user->getUsername() );
		if( $existingByUsername && $existingByUsername->getId() !== $user->getId() )
		{
			throw new DuplicateEntityException( 'User', 'username', $user->getUsername() );
		}

		// Check for duplicate email (excluding current user)
		$existingByEmail = $this->findByEmail( $user->getEmail() );
		if( $existingByEmail && $existingByEmail->getId() !== $user->getId() )
		{
			throw new DuplicateEntityException( 'User', 'email', $user->getEmail() );
		}

		// Update timestamp (database-independent approach)
		$user->setUpdatedAt( new \DateTimeImmutable() );

		// Use ORM save method
		return $user->save();
	}

	/**
	 * Delete a user
	 */
	public function delete( int $id ): bool
	{
		$deletedCount = User::query()->where( 'id', $id )->delete();

		return $deletedCount > 0;
	}

	/**
	 * Get all users
	 */
	public function all(): array
	{
		return User::orderBy( 'created_at', 'DESC' )->all();
	}

	/**
	 * Count total users
	 */
	public function count(): int
	{
		return User::query()->count();
	}

	/**
	 * Atomically increment failed login attempts for a user
	 *
	 * Uses atomic UPDATE to avoid race condition under concurrent login attempts.
	 *
	 * @param int $userId User ID
	 * @return int New failed login attempts count, or -1 if user not found
	 */
	public function incrementFailedLoginAttempts( int $userId ): int
	{
		// Use atomic increment with updated_at timestamp
		$rowsUpdated = User::query()
			->where( 'id', $userId )
			->increment( 'failed_login_attempts', 1, [
				'updated_at' => ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
			]);

		if( $rowsUpdated === 0 )
		{
			return -1; // User not found
		}

		// Fetch and return the new count
		$user = $this->findById( $userId );
		return $user ? $user->getFailedLoginAttempts() : -1;
	}

	/**
	 * Atomically reset failed login attempts and unlock account
	 *
	 * Uses atomic UPDATE to avoid race condition.
	 *
	 * @param int $userId User ID
	 * @return bool True if successful, false if user not found
	 */
	public function resetFailedLoginAttempts( int $userId ): bool
	{
		// Use ORM's atomic update to avoid race condition
		$rowsUpdated = User::query()
			->where( 'id', $userId )
			->update([
				'failed_login_attempts' => 0,
				'locked_until' => null,
				'updated_at' => ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
			]);

		return $rowsUpdated > 0;
	}

	/**
	 * Atomically set account lockout until specified time
	 *
	 * Uses atomic UPDATE to avoid race condition.
	 *
	 * @param int $userId User ID
	 * @param \DateTimeImmutable|null $lockedUntil Locked until time, or null to unlock
	 * @return bool True if successful, false if user not found
	 */
	public function setLockedUntil( int $userId, ?\DateTimeImmutable $lockedUntil ): bool
	{
		$lockedUntilString = $lockedUntil ? $lockedUntil->format( 'Y-m-d H:i:s' ) : null;

		// Use ORM's atomic update to avoid race condition
		$rowsUpdated = User::query()
			->where( 'id', $userId )
			->update([
				'locked_until' => $lockedUntilString,
				'updated_at' => ( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
			]);

		return $rowsUpdated > 0;
	}

	/**
	 * Handle serialization for PHPUnit process isolation
	 */
	public function __sleep(): array
	{
		// Don't serialize PDO connection
		return [];
	}

	/**
	 * Handle unserialization for PHPUnit process isolation
	 */
	public function __wakeup(): void
	{
		// PDO will be re-initialized by test setup
	}
}
