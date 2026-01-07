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
	 * Supports both URL-based configuration and individual parameters.
	 * If 'url' is provided, it will be parsed and merged with individual parameters.
	 * Individual parameters take precedence over URL-parsed values.
	 *
	 * @param array $config Database configuration
	 * @return PDO
	 * @throws Exception if adapter is unsupported or configuration is invalid
	 */
	public static function createFromConfig( array $config ): PDO
	{
		// If URL is provided, parse it and merge with config
		if( !empty( $config['url'] ) )
		{
			$urlConfig = self::parseUrl( $config['url'] );
			// Merge URL config with provided config (provided config takes precedence)
			$config = array_merge( $urlConfig, array_filter( $config, function( $value, $key ) {
				// Keep non-null values that aren't the URL itself
				return $key !== 'url' && $value !== null;
			}, ARRAY_FILTER_USE_BOTH ) );
		}

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
			$config['user'] ?? $config['username'] ?? null,
			$config['pass'] ?? $config['password'] ?? null,
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
	 * Parse a database URL into configuration array
	 *
	 * Supports common database URL formats:
	 * - postgresql://user:pass@host:5432/dbname?option=value
	 * - mysql://user:pass@host:3306/dbname?charset=utf8mb4
	 * - sqlite:///path/to/database.db or sqlite::memory:
	 *
	 * @param string $url Database URL
	 * @return array Parsed configuration
	 * @throws Exception if URL is malformed
	 */
	private static function parseUrl( string $url ): array
	{
		// Handle SQLite's special cases first
		if( str_starts_with( $url, 'sqlite:' ) )
		{
			// Remove sqlite: prefix
			$path = substr( $url, 7 );

			// Handle special :memory: database
			if( $path === ':memory:' )
			{
				return [
					'adapter' => 'sqlite',
					'name' => ':memory:'
				];
			}

			// Handle absolute paths (sqlite:///path or sqlite://path)
			// Check triple slash first (absolute path)
			if( str_starts_with( $path, '///' ) )
			{
				// Three slashes means absolute path
				$path = substr( $path, 2 );
			}
			elseif( str_starts_with( $path, '//' ) )
			{
				// Two slashes, could be relative or network path
				$path = substr( $path, 2 );
			}

			return [
				'adapter' => 'sqlite',
				'name' => $path
			];
		}

		// Parse URL for other databases
		$parsed = parse_url( $url );

		if( $parsed === false || !isset( $parsed['scheme'] ) )
		{
			throw new Exception( "Malformed database URL: $url" );
		}

		// Map URL scheme to adapter
		$adapter = match( $parsed['scheme'] )
		{
			'mysql' => 'mysql',
			'postgresql', 'postgres', 'pgsql' => 'pgsql',
			default => throw new Exception( "Unsupported database scheme: {$parsed['scheme']}" )
		};

		$config = [ 'adapter' => $adapter ];

		// Extract host, port, user, password
		if( isset( $parsed['host'] ) )
		{
			$config['host'] = $parsed['host'];
		}

		if( isset( $parsed['port'] ) )
		{
			$config['port'] = $parsed['port'];
		}

		if( isset( $parsed['user'] ) )
		{
			// Decode URL-encoded characters (e.g., %40 for @)
			$config['user'] = rawurldecode( $parsed['user'] );
		}

		if( isset( $parsed['pass'] ) )
		{
			// Decode URL-encoded characters (e.g., %40 for @)
			$config['pass'] = rawurldecode( $parsed['pass'] );
		}

		// Extract database name from path
		if( isset( $parsed['path'] ) && $parsed['path'] !== '/' )
		{
			// Remove leading slash
			$config['name'] = ltrim( $parsed['path'], '/' );
		}

		// Parse query parameters for additional options
		if( isset( $parsed['query'] ) )
		{
			parse_str( $parsed['query'], $queryParams );

			// Common query parameters
			if( isset( $queryParams['charset'] ) )
			{
				$config['charset'] = $queryParams['charset'];
			}

			// You can add more query parameter mappings here as needed
		}

		return $config;
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
