<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\EmailVerificationToken;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;
use DateTimeImmutable;

/**
 * Database-backed email verification token repository.
 *
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseEmailVerificationTokenRepository implements IEmailVerificationTokenRepository
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
	 * Create a new email verification token
	 */
	public function create( EmailVerificationToken $token ): EmailVerificationToken
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at)
			 VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$token->getUserId(),
			$token->getToken(),
			$token->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			$token->getExpiresAt()->format( 'Y-m-d H:i:s' )
		]);

		$token->setId( (int) $this->_pdo->lastInsertId() );

		return $token;
	}

	/**
	 * Find a token by its hashed value
	 */
	public function findByToken( string $token ): ?EmailVerificationToken
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM email_verification_tokens WHERE token = ? LIMIT 1" );
		$stmt->execute( [ $token ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToToken( $row ) : null;
	}

	/**
	 * Find most recent token by user ID
	 */
	public function findByUserId( int $userId ): ?EmailVerificationToken
	{
		$stmt = $this->_pdo->prepare(
			"SELECT * FROM email_verification_tokens
			 WHERE user_id = ?
			 ORDER BY created_at DESC
			 LIMIT 1"
		);
		$stmt->execute( [ $userId ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToToken( $row ) : null;
	}

	/**
	 * Delete all tokens for a given user ID
	 */
	public function deleteByUserId( int $userId ): int
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM email_verification_tokens WHERE user_id = ?" );
		$stmt->execute( [ $userId ] );

		return $stmt->rowCount();
	}

	/**
	 * Delete a specific token by its hashed value
	 */
	public function deleteByToken( string $token ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM email_verification_tokens WHERE token = ?" );
		$stmt->execute( [ $token ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Delete all expired tokens
	 */
	public function deleteExpired(): int
	{
		$now = (new DateTimeImmutable())->format( 'Y-m-d H:i:s' );

		$stmt = $this->_pdo->prepare( "DELETE FROM email_verification_tokens WHERE expires_at < ?" );
		$stmt->execute( [ $now ] );

		return $stmt->rowCount();
	}

	/**
	 * Map database row to EmailVerificationToken object
	 */
	private function mapRowToToken( array $row ): EmailVerificationToken
	{
		return EmailVerificationToken::fromArray([
			'id' => $row['id'],
			'user_id' => $row['user_id'],
			'token' => $row['token'],
			'created_at' => $row['created_at'],
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
