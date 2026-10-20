<?php

namespace Neuron\Cms\Database;

use Neuron\Data\Database\ConnectionFactory as DataConnectionFactory;
use Neuron\Data\Settings\SettingManager;
use PDO;
use Exception;

/**
 * Factory for creating PDO database connections.
 *
 * Thin CMS-facing wrapper over {@see \Neuron\Data\Database\ConnectionFactory},
 * which owns URL parsing, DSN construction and per-adapter initialisation so
 * that the CMS, the job queue and any other component build connections
 * identically. This class remains for backwards compatibility and for the
 * settings-aware entry point.
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
		try
		{
			return DataConnectionFactory::createFromConfig( $config );
		}
		catch( \InvalidArgumentException $e )
		{
			throw new Exception( $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Parse a database URL into configuration array
	 *
	 * @param string $url Database URL
	 * @return array Parsed configuration
	 * @throws Exception if URL is malformed
	 * @see \Neuron\Data\Database\ConnectionFactory::parseUrl()
	 */
	private static function parseUrl( string $url ): array
	{
		try
		{
			return DataConnectionFactory::parseUrl( $url );
		}
		catch( \InvalidArgumentException $e )
		{
			throw new Exception( $e->getMessage(), 0, $e );
		}
	}
}
