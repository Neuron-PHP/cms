<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Data\Settings\SettingManager;
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
	 * Create a new password reset token
	 */
	public function create( PasswordResetToken $token ): PasswordResetToken
	{
		$stmt = $this->_pdo->prepare(
			"INSERT INTO password_reset_tokens (email, token, created_at, expires_at)
			 VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$token->getEmail(),
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
	public function findByToken( string $token ): ?PasswordResetToken
	{
		$stmt = $this->_pdo->prepare( "SELECT * FROM password_reset_tokens WHERE token = ? LIMIT 1" );
		$stmt->execute( [ $token ] );

		$row = $stmt->fetch();

		return $row ? $this->mapRowToToken( $row ) : null;
	}

	/**
	 * Delete all tokens for a given email address
	 */
	public function deleteByEmail( string $email ): int
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM password_reset_tokens WHERE email = ?" );
		$stmt->execute( [ $email ] );

		return $stmt->rowCount();
	}

	/**
	 * Delete a specific token by its hashed value
	 */
	public function deleteByToken( string $token ): bool
	{
		$stmt = $this->_pdo->prepare( "DELETE FROM password_reset_tokens WHERE token = ?" );
		$stmt->execute( [ $token ] );

		return $stmt->rowCount() > 0;
	}

	/**
	 * Delete all expired tokens
	 */
	public function deleteExpired(): int
	{
		$now = (new DateTimeImmutable())->format( 'Y-m-d H:i:s' );

		$stmt = $this->_pdo->prepare( "DELETE FROM password_reset_tokens WHERE expires_at < ?" );
		$stmt->execute( [ $now ] );

		return $stmt->rowCount();
	}

	/**
	 * Map database row to PasswordResetToken object
	 */
	private function mapRowToToken( array $row ): PasswordResetToken
	{
		return PasswordResetToken::fromArray([
			'id' => $row['id'],
			'email' => $row['email'],
			'token' => $row['token'],
			'created_at' => $row['created_at'],
			'expires_at' => $row['expires_at']
		]);
	}
}
