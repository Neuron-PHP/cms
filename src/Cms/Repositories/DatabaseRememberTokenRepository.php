<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\RememberToken;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed remember token repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseRememberTokenRepository implements IRememberTokenRepository
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
	 * Create a new remember token
	 */
	public function create( RememberToken $token ): RememberToken
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO remember_tokens (user_id, token, created_at, last_used_at, expires_at)
			 VALUES (?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			$token->getUserId(),
			$token->getToken(),
			$token->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			$token->getLastUsedAt()?->format( 'Y-m-d H:i:s' ),
			$token->getExpiresAt()->format( 'Y-m-d H:i:s' )
		]);

		$token->setId( (int) $this->_pdo->lastInsertId() );

		return $token;
	}

	/**
	 * Find an unexpired token by its hashed value
	 */
	public function findValidByToken( string $token ): ?RememberToken
	{
		$now = (new DateTimeImmutable())->format( 'Y-m-d H:i:s' );

		$stmt = $this->_pdo->prepare( "SELECT * FROM remember_tokens WHERE token = ? AND expires_at > ? LIMIT 1" );
		$stmt->execute( [ $token, $now ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToToken( $row ) : null;
	}

	/**
	 * Record that a token was just used to sign in
	 */
	public function touch( RememberToken $token ): void
	{
		$now = new DateTimeImmutable();

		$stmt = $this->_pdo->prepare( "UPDATE remember_tokens SET last_used_at = ? WHERE token = ?" );
		$stmt->execute( [ $now->format( 'Y-m-d H:i:s' ), $token->getToken() ] );

		$token->setLastUsedAt( $now );
	}

	/**
	 * Delete a specific token by its hashed value
	 */
	public function deleteByToken( string $token ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM remember_tokens WHERE token = ?" );
		$stmt->execute( [ $token ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Delete every token for a user
	 */
	public function deleteByUserId( int $userId ): int
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM remember_tokens WHERE user_id = ?" );
		$stmt->execute( [ $userId ] );

		return $stmt->rowCount();
	}

	/**
	 * Delete all expired tokens
	 */
	public function deleteExpired(): int
	{
		$now = (new DateTimeImmutable())->format( 'Y-m-d H:i:s' );

		$stmt = $this->_pdo->prepare( "DELETE FROM remember_tokens WHERE expires_at < ?" );
		$stmt->execute( [ $now ] );

		return $stmt->rowCount();
	}

	/**
	 * Map database row to RememberToken object
	 */
	private function mapRowToToken( array $row ): RememberToken
	{
		return RememberToken::fromArray([
			'id' => $row['id'],
			'user_id' => $row['user_id'],
			'token' => $row['token'],
			'created_at' => $row['created_at'],
			'last_used_at' => $row['last_used_at'] ?? null,
			'expires_at' => $row['expires_at']
		]);
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
