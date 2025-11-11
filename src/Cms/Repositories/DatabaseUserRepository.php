<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\User;
use Neuron\Data\Setting\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed user repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseUserRepository implements IUserRepository
{
	private PDO $_pdo;

	/**
	 * Constructor
	 *
	 * @param SettingManager $settings Settings manager with database configuration
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Find user by ID
	 */
	public function findById( int $id ): ?User
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $id ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by username
	 */
	public function findByUsername( string $username ): ?User
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE username = ? LIMIT 1" );
		$stmt->execute( [ $username ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by email
	 */
	public function findByEmail( string $email ): ?User
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE email = ? LIMIT 1" );
		$stmt->execute( [ $email ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by remember token
	 */
	public function findByRememberToken( string $token ): ?User
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM users WHERE remember_token = ? LIMIT 1" );
		$stmt->execute( [ $token ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
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

		$stmt = $this->_pdo->prepare(
			"INSERT INTO users (
				username, email, password_hash, role, status, email_verified,
				two_factor_secret, remember_token, failed_login_attempts,
				locked_until, last_login_at, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$user->getUsername(),
			$user->getEmail(),
			$user->getPasswordHash(),
			$user->getRole(),
			$user->getStatus(),
			$user->isEmailVerified() ? 1 : 0,
			$user->getTwoFactorSecret(),
			$user->getRememberToken(),
			$user->getFailedLoginAttempts(),
			$user->getLockedUntil() ? $user->getLockedUntil()->format( 'Y-m-d H:i:s' ) : null,
			$user->getLastLoginAt() ? $user->getLastLoginAt()->format( 'Y-m-d H:i:s' ) : null,
			$user->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$user->setId( (int)$this->_pdo->lastInsertId() );

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

		$stmt = $this->_pdo->prepare(
			"UPDATE users SET
				username = ?,
				email = ?,
				password_hash = ?,
				role = ?,
				status = ?,
				email_verified = ?,
				two_factor_secret = ?,
				remember_token = ?,
				failed_login_attempts = ?,
				locked_until = ?,
				last_login_at = ?,
				updated_at = ?
			WHERE id = ?"
		);

		return $stmt->execute([
			$user->getUsername(),
			$user->getEmail(),
			$user->getPasswordHash(),
			$user->getRole(),
			$user->getStatus(),
			$user->isEmailVerified() ? 1 : 0,
			$user->getTwoFactorSecret(),
			$user->getRememberToken(),
			$user->getFailedLoginAttempts(),
			$user->getLockedUntil() ? $user->getLockedUntil()->format( 'Y-m-d H:i:s' ) : null,
			$user->getLastLoginAt() ? $user->getLastLoginAt()->format( 'Y-m-d H:i:s' ) : null,
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$user->getId()
		]);
	}

	/**
	 * Delete a user
	 */
	public function delete( int $id ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [ $id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all users
	 */
	public function all(): array
	{
		$stmt = $this->_pdo->query( "SELECT * FROM users ORDER BY created_at DESC" );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToUser' ], $rows );
	}

	/**
	 * Count total users
	 */
	public function count(): int
	{
		$stmt = $this->_pdo->query( "SELECT COUNT(*) as total FROM users" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Map database row to User object
	 *
	 * @param array $row Database row
	 * @return User
	 */
	private function mapRowToUser( array $row ): User
	{
		$emailVerifiedRaw = $row['email_verified'] ?? null;
		$emailVerified = is_bool( $emailVerifiedRaw )
			? $emailVerifiedRaw
			: in_array(
				strtolower( (string)$emailVerifiedRaw ),
				[ '1', 'true', 't', 'yes', 'on' ],
				true
			);

		$data = [
			'id' => (int)$row['id'],
			'username' => $row['username'],
			'email' => $row['email'],
			'password_hash' => $row['password_hash'],
			'role' => $row['role'],
			'status' => $row['status'],
			'email_verified' => $emailVerified,
			'two_factor_secret' => $row['two_factor_secret'],
			'remember_token' => $row['remember_token'],
			'failed_login_attempts' => (int)$row['failed_login_attempts'],
			'locked_until' => $row['locked_until'] ?? null,
			'last_login_at' => $row['last_login_at'] ?? null,
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'] ?? null,
		];

		return User::fromArray( $data );
	}
}
