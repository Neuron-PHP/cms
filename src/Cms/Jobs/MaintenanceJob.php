<?php

namespace Neuron\Cms\Jobs;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Jobs\IJob;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use PDO;
use Throwable;

/**
 * Base class for CMS maintenance jobs.
 *
 * CMS maintenance jobs are scheduled via config/schedule.yaml and run by the
 * Neuron scheduler. The scheduler instantiates jobs with no constructor
 * arguments ( new $class() ), so jobs resolve their own dependencies from the
 * Registry rather than via dependency injection.
 *
 * @package Neuron\Cms\Jobs
 */
abstract class MaintenanceJob implements IJob
{
	/**
	 * Resolve the application SettingManager from the Registry.
	 *
	 * @return SettingManager|null Null when settings are unavailable.
	 */
	protected function getSettings(): ?SettingManager
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		return $settings instanceof SettingManager ? $settings : null;
	}

	/**
	 * Build a PDO connection from the application database settings.
	 *
	 * @return PDO|null Null when the connection cannot be established.
	 */
	protected function getConnection(): ?PDO
	{
		$settings = $this->getSettings();

		if( $settings === null )
		{
			Log::warning( "{$this->getName()}: settings unavailable; skipping." );
			return null;
		}

		try
		{
			return ConnectionFactory::createFromSettings( $settings );
		}
		catch( Throwable $exception )
		{
			Log::warning( "{$this->getName()}: database unavailable ({$exception->getMessage()}); skipping." );
			return null;
		}
	}

	/**
	 * Resolve the application base path from the Registry.
	 *
	 * @return string|null
	 */
	protected function getBasePath(): ?string
	{
		$basePath = Registry::getInstance()->get( RegistryKeys::BASE_PATH );

		return $basePath ? rtrim( (string)$basePath, '/' ) : null;
	}

	/**
	 * Read an integer argument with a default fallback.
	 *
	 * @param array $argv
	 * @param string $key
	 * @param int $default
	 * @return int
	 */
	protected function intArg( array $argv, string $key, int $default ): int
	{
		if( !isset( $argv[ $key ] ) || !is_numeric( $argv[ $key ] ) )
		{
			return $default;
		}

		return (int)$argv[ $key ];
	}
}
