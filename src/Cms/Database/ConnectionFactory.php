<?php

namespace Neuron\Cms\Database;

use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Factory for creating PDO database connections.
 *
 * Centralizes database connection logic and supports multiple adapters
 * (SQLite, MySQL, PostgreSQL).
 *
 * @package Neuron\Cms\Database
 */
class ConnectionFactory
{
	/**
	 * Create a PDO connection from SettingManager
	 *
	 * @param SettingManager $settings
	 * @return PDO
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public static function createFromSettings( SettingManager $settings ): PDO
	{
		$config = $settings->getSection( 'database' );

		if( !$config )
		{
			throw new Exception( 'Database configuration not found in settings' );
		}

		return self::createFromConfig( $config );
	}

	/**
	 * Create a PDO connection from configuration array
	 *
	 * @param array $config Database configuration
	 * @return PDO
	 * @throws Exception if adapter is unsupported
	 */
	public static function createFromConfig( array $config ): PDO
	{
		$adapter = $config['adapter'] ?? 'sqlite';

		if( empty( $config['name'] ) )
		{
			throw new Exception(
				sprintf(
					'Database "name" configuration is required for %s connections.',
					$adapter
				)
			);
		}

		$dsn = match( $adapter )
		{
			'sqlite' => "sqlite:{$config['name']}",
			'mysql' => sprintf(
				"mysql:host=%s;port=%s;dbname=%s;charset=%s",
				$config['host'] ?? 'localhost',
				$config['port'] ?? 3306,
				$config['name'],
				$config['charset'] ?? 'utf8mb4'
			),
			'pgsql' => sprintf(
				"pgsql:host=%s;port=%s;dbname=%s",
				$config['host'] ?? 'localhost',
				$config['port'] ?? 5432,
				$config['name']
			),
			default => throw new Exception( "Unsupported database adapter: $adapter" )
		};

		$pdo = new PDO(
			$dsn,
			$config['user'] ?? null,
			$config['pass'] ?? null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);

		// Apply database-specific initialization
		self::initializeConnection( $pdo, $adapter );

		return $pdo;
	}

	/**
	 * Initialize database-specific settings
	 *
	 * @param PDO $pdo
	 * @param string $adapter
	 * @return void
	 */
	private static function initializeConnection( PDO $pdo, string $adapter ): void
	{
		match( $adapter )
		{
			'sqlite' => self::initializeSqlite( $pdo ),
			'mysql' => self::initializeMysql( $pdo ),
			'pgsql' => self::initializePostgresql( $pdo ),
			default => null
		};
	}

	/**
	 * Initialize SQLite-specific settings
	 *
	 * @param PDO $pdo
	 * @return void
	 */
	private static function initializeSqlite( PDO $pdo ): void
	{
		// Enable foreign key constraints (disabled by default in SQLite)
		$pdo->exec( 'PRAGMA foreign_keys = ON' );

		// Enable WAL mode for better concurrency
		$pdo->exec( 'PRAGMA journal_mode = WAL' );

		// Set busy timeout to handle locks gracefully (5 seconds)
		$pdo->exec( 'PRAGMA busy_timeout = 5000' );
	}

	/**
	 * Initialize MySQL-specific settings
	 *
	 * @param PDO $pdo
	 * @return void
	 */
	private static function initializeMysql( PDO $pdo ): void
	{
		// Set timezone to UTC for consistent timestamp handling
		$pdo->exec( "SET time_zone = '+00:00'" );
	}

	/**
	 * Initialize PostgreSQL-specific settings
	 *
	 * @param PDO $pdo
	 * @return void
	 */
	private static function initializePostgresql( PDO $pdo ): void
	{
		// Set character encoding to UTF8
		$pdo->exec( "SET NAMES 'UTF8'" );

		// Set timezone to UTC for consistent timestamp handling
		$pdo->exec( "SET timezone = 'UTC'" );
	}
}
