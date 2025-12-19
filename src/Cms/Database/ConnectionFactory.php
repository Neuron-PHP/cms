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

		return new PDO(
			$dsn,
			$config['user'] ?? null,
			$config['pass'] ?? null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);
	}
}
