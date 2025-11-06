<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\User;
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
	private PDO $_PDO;

	/**
	 * Constructor
	 *
	 * @param array $DatabaseConfig Database configuration
	 * @throws Exception if adapter is not supported
	 */
	public function __construct( array $DatabaseConfig )
	{
		$adapter = $DatabaseConfig['adapter'] ?? 'sqlite';

		$dsn = match( $adapter )
		{
			'sqlite' => "sqlite:{$DatabaseConfig['name']}",
			'mysql' => sprintf(
				"mysql:host=%s;port=%s;dbname=%s;charset=%s",
				$DatabaseConfig['host'] ?? 'localhost',
				$DatabaseConfig['port'] ?? 3306,
				$DatabaseConfig['name'],
				$DatabaseConfig['charset'] ?? 'utf8mb4'
			),
			'pgsql' => sprintf(
				"pgsql:host=%s;port=%s;dbname=%s",
				$DatabaseConfig['host'] ?? 'localhost',
				$DatabaseConfig['port'] ?? 5432,
				$DatabaseConfig['name']
			),
			default => throw new Exception( "Unsupported database adapter: $adapter" )
		};

		$this->_PDO = new PDO(
			$dsn,
			$DatabaseConfig['user'] ?? null,
			$DatabaseConfig['pass'] ?? null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);
	}

	/**
	 * Find user by ID
	 */
	public function findById( int $Id ): ?User
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM users WHERE id = ? LIMIT 1" );
		$stmt->execute( [ $Id ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by username
	 */
	public function findByUsername( string $Username ): ?User
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM users WHERE username = ? LIMIT 1" );
		$stmt->execute( [ $Username ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by email
	 */
	public function findByEmail( string $Email ): ?User
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM users WHERE email = ? LIMIT 1" );
		$stmt->execute( [ $Email ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Find user by remember token
	 */
	public function findByRememberToken( string $Token ): ?User
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM users WHERE remember_token = ? LIMIT 1" );
		$stmt->execute( [ $Token ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToUser( $row ) : null;
	}

	/**
	 * Create a new user
	 */
	public function create( User $User ): User
	{
		// Check for duplicate username
		if( $this->findByUsername( $User->getUsername() ) )
		{
			throw new Exception( 'Username already exists' );
		}

		// Check for duplicate email
		if( $this->findByEmail( $User->getEmail() ) )
		{
			throw new Exception( 'Email already exists' );
		}

		$stmt = $this->_PDO->prepare(
			"INSERT INTO users (
				username, email, password_hash, role, status, email_verified,
				two_factor_secret, remember_token, failed_login_attempts,
				locked_until, last_login_at, created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$User->getUsername(),
			$User->getEmail(),
			$User->getPasswordHash(),
			$User->getRole(),
			$User->getStatus(),
			$User->isEmailVerified() ? 1 : 0,
			$User->getTwoFactorSecret(),
			$User->getRememberToken(),
			$User->getFailedLoginAttempts(),
			$User->getLockedUntil() ? $User->getLockedUntil()->format( 'Y-m-d H:i:s' ) : null,
			$User->getLastLoginAt() ? $User->getLastLoginAt()->format( 'Y-m-d H:i:s' ) : null,
			$User->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' )
		]);

		$User->setId( (int)$this->_PDO->lastInsertId() );

		return $User;
	}

	/**
	 * Update an existing user
	 */
	public function update( User $User ): bool
	{
		if( !$User->getId() )
		{
			return false;
		}

		// Check for duplicate username (excluding current user)
		$ExistingByUsername = $this->findByUsername( $User->getUsername() );
		if( $ExistingByUsername && $ExistingByUsername->getId() !== $User->getId() )
		{
			throw new Exception( 'Username already exists' );
		}

		// Check for duplicate email (excluding current user)
		$ExistingByEmail = $this->findByEmail( $User->getEmail() );
		if( $ExistingByEmail && $ExistingByEmail->getId() !== $User->getId() )
		{
			throw new Exception( 'Email already exists' );
		}

		$stmt = $this->_PDO->prepare(
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
			$User->getUsername(),
			$User->getEmail(),
			$User->getPasswordHash(),
			$User->getRole(),
			$User->getStatus(),
			$User->isEmailVerified() ? 1 : 0,
			$User->getTwoFactorSecret(),
			$User->getRememberToken(),
			$User->getFailedLoginAttempts(),
			$User->getLockedUntil() ? $User->getLockedUntil()->format( 'Y-m-d H:i:s' ) : null,
			$User->getLastLoginAt() ? $User->getLastLoginAt()->format( 'Y-m-d H:i:s' ) : null,
			(new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			$User->getId()
		]);
	}

	/**
	 * Delete a user
	 */
	public function delete( int $Id ): bool
	{
		$stmt = $this->_PDO->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [ $Id ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Get all users
	 */
	public function all(): array
	{
		$stmt = $this->_PDO->query( "SELECT * FROM users ORDER BY created_at DESC" );
		$rows = $stmt->fetchAll();

		return array_map( [ $this, 'mapRowToUser' ], $rows );
	}

	/**
	 * Count total users
	 */
	public function count(): int
	{
		$stmt = $this->_PDO->query( "SELECT COUNT(*) as total FROM users" );
		$row = $stmt->fetch();

		return (int)$row['total'];
	}

	/**
	 * Map database row to User object
	 *
	 * @param array $Row Database row
	 * @return User
	 */
	private function mapRowToUser( array $Row ): User
	{
		$data = [
			'id' => (int)$Row['id'],
			'username' => $Row['username'],
			'email' => $Row['email'],
			'password_hash' => $Row['password_hash'],
			'role' => $Row['role'],
			'status' => $Row['status'],
			'email_verified' => (bool)$Row['email_verified'],
			'two_factor_secret' => $Row['two_factor_secret'],
			'remember_token' => $Row['remember_token'],
			'failed_login_attempts' => (int)$Row['failed_login_attempts'],
			'locked_until' => $Row['locked_until'] ?? null,
			'last_login_at' => $Row['last_login_at'] ?? null,
			'created_at' => $Row['created_at'],
			'updated_at' => $Row['updated_at'] ?? null,
		];

		return User::fromArray( $data );
	}
}
