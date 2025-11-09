<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\PasswordResetToken;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed password reset token repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabasePasswordResetTokenRepository implements IPasswordResetTokenRepository
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
	 * Create a new password reset token
	 */
	public function create( PasswordResetToken $Token ): PasswordResetToken
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO password_reset_tokens (email, token, created_at, expires_at)
			 VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$Token->getEmail(),
			$Token->getToken(),
			$Token->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			$Token->getExpiresAt()->format( 'Y-m-d H:i:s' )
		]);

		$Token->setId( (int) $this->_PDO->lastInsertId() );

		return $Token;
	}

	/**
	 * Find a token by its hashed value
	 */
	public function findByToken( string $Token ): ?PasswordResetToken
	{
		$stmt = $this->_PDO->prepare( "SELECT * FROM password_reset_tokens WHERE token = ? LIMIT 1" );
		$stmt->execute( [ $Token ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToToken( $row ) : null;
	}

	/**
	 * Delete all tokens for a given email address
	 */
	public function deleteByEmail( string $Email ): int
	{
		$stmt = $this->_PDO->prepare( "DELETE FROM password_reset_tokens WHERE email = ?" );
		$stmt->execute( [ $Email ] );

		return $stmt->rowCount();
	}

	/**
	 * Delete a specific token by its hashed value
	 */
	public function deleteByToken( string $Token ): bool
	{
		$stmt = $this->_PDO->prepare( "DELETE FROM password_reset_tokens WHERE token = ?" );
		$stmt->execute( [ $Token ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Delete all expired tokens
	 */
	public function deleteExpired(): int
	{
		$now = (new DateTimeImmutable())->format( 'Y-m-d H:i:s' );

		$stmt = $this->_PDO->prepare( "DELETE FROM password_reset_tokens WHERE expires_at < ?" );
		$stmt->execute( [ $now ] );

		return $stmt->rowCount();
	}

	/**
	 * Map database row to PasswordResetToken object
	 */
	private function mapRowToToken( array $Row ): PasswordResetToken
	{
		return PasswordResetToken::fromArray([
			'id' => $Row['id'],
			'email' => $Row['email'],
			'token' => $Row['token'],
			'created_at' => $Row['created_at'],
			'expires_at' => $Row['expires_at']
		]);
	}
}
