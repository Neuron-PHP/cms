<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\User;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Database-backed user repository using ORM.
 *
 * Works with SQLite, MySQL, and PostgreSQL via the Neuron ORM.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseUserRepository implements IUserRepository
{
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
			throw new Exception( 'Username already exists' );
		}

		// Check for duplicate email
		if( $this->findByEmail( $user->getEmail() ) )
		{
			throw new Exception( 'Email already exists' );
		}

		// Use ORM create method
		$createdUser = User::create( $user->toArray() );

		// Update the original user with the new ID
		$user->setId( $createdUser->getId() );

		return $user;
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
			throw new Exception( 'Username already exists' );
		}

		// Check for duplicate email (excluding current user)
		$existingByEmail = $this->findByEmail( $user->getEmail() );
		if( $existingByEmail && $existingByEmail->getId() !== $user->getId() )
		{
			throw new Exception( 'Email already exists' );
		}

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
}
