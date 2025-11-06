<?php

namespace Neuron\Cms\Database;

use Neuron\Data\Setting\Source\ISettingSource;
use Phinx\Config\Config;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Manages database migrations using Phinx
 * Bridges Neuron configuration to Phinx
 */
class MigrationManager
{
	private string $_BasePath;
	private ?ISettingSource $_SettingSource;
	private ?Config $_PhinxConfig = null;

	/**
	 * @param string $BasePath Application base path
	 * @param ISettingSource|null $SettingSource Neuron settings source
	 */
	public function __construct( string $BasePath, ?ISettingSource $SettingSource = null )
	{
		$this->_BasePath = rtrim( $BasePath, '/' );
		$this->_SettingSource = $SettingSource;
	}

	/**
	 * Get Phinx configuration from Neuron settings
	 *
	 * @return Config
	 */
	public function getPhinxConfig(): Config
	{
		if( $this->_PhinxConfig !== null )
		{
			return $this->_PhinxConfig;
		}

		$config = $this->buildPhinxConfig();
		$this->_PhinxConfig = new Config( $config );

		return $this->_PhinxConfig;
	}

	/**
	 * Build Phinx configuration array from Neuron settings
	 *
	 * @return array
	 */
	private function buildPhinxConfig(): array
	{
		$migrationsPath = $this->getMigrationsPath();
		$seedsPath = $this->getSeedsPath();
		$migrationTable = $this->getMigrationTable();

		// Build paths array
		$paths = [
			'migrations' => $migrationsPath,
			'seeds' => $seedsPath
		];

		// Build environments configuration
		$environments = [
			'default_migration_table' => $migrationTable,
			'default_environment' => $this->getEnvironment(),
			$this->getEnvironment() => $this->getDatabaseConfig()
		];

		return [
			'paths' => $paths,
			'environments' => $environments,
			'version_order' => 'creation'
		];
	}

	/**
	 * Get database configuration for Phinx
	 *
	 * @return array
	 */
	private function getDatabaseConfig(): array
	{
		if( !$this->_SettingSource )
		{
			return $this->getDefaultDatabaseConfig();
		}

		try
		{
			$adapter = $this->getSetting( 'database', 'adapter', 'mysql' );
			$host = $this->getSetting( 'database', 'host', 'localhost' );
			$name = $this->getSetting( 'database', 'name', 'neuron_cms' );
			$user = $this->getSetting( 'database', 'user', 'root' );
			$pass = $this->getSetting( 'database', 'pass', '' );
			$port = $this->getSetting( 'database', 'port', 3306 );
			$charset = $this->getSetting( 'database', 'charset', 'utf8mb4' );

			return [
				'adapter' => $adapter,
				'host' => $host,
				'name' => $name,
				'user' => $user,
				'pass' => $pass,
				'port' => (int)$port,
				'charset' => $charset
			];
		}
		catch( \Exception $e )
		{
			return $this->getDefaultDatabaseConfig();
		}
	}

	/**
	 * Get default database configuration
	 *
	 * @return array
	 */
	private function getDefaultDatabaseConfig(): array
	{
		return [
			'adapter' => 'mysql',
			'host' => 'localhost',
			'name' => 'neuron_cms',
			'user' => 'root',
			'pass' => '',
			'port' => 3306,
			'charset' => 'utf8mb4'
		];
	}

	/**
	 * Get migrations directory path
	 *
	 * @return string
	 */
	public function getMigrationsPath(): string
	{
		$path = $this->getSetting( 'migrations', 'path', 'db/migrate' );

		return $this->resolvePath( $path );
	}

	/**
	 * Get seeds directory path
	 *
	 * @return string
	 */
	public function getSeedsPath(): string
	{
		$path = $this->getSetting( 'migrations', 'seeds_path', 'db/seed' );

		return $this->resolvePath( $path );
	}

	/**
	 * Get migration tracking table name
	 *
	 * @return string
	 */
	public function getMigrationTable(): string
	{
		return $this->getSetting( 'migrations', 'table', 'phinx_log' );
	}

	/**
	 * Get environment name
	 *
	 * @return string
	 */
	public function getEnvironment(): string
	{
		return $this->getSetting( 'system', 'environment', 'development' );
	}

	/**
	 * Resolve path relative to base path
	 *
	 * @param string $path
	 * @return string
	 */
	private function resolvePath( string $path ): string
	{
		// If absolute path, use as-is
		if( str_starts_with( $path, '/' ) )
		{
			return $path;
		}

		// Relative to base path
		return $this->_BasePath . '/' . $path;
	}

	/**
	 * Get setting value
	 *
	 * @param string $section
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private function getSetting( string $section, string $key, mixed $default ): mixed
	{
		if( !$this->_SettingSource )
		{
			return $default;
		}

		try
		{
			$value = $this->_SettingSource->get( $section, $key );
			return $value ?? $default;
		}
		catch( \Exception $e )
		{
			return $default;
		}
	}

	/**
	 * Ensure migrations directory exists
	 *
	 * @return bool
	 */
	public function ensureMigrationsDirectory(): bool
	{
		$path = $this->getMigrationsPath();

		if( !is_dir( $path ) )
		{
			return mkdir( $path, 0755, true );
		}

		return true;
	}

	/**
	 * Ensure seeds directory exists
	 *
	 * @return bool
	 */
	public function ensureSeedsDirectory(): bool
	{
		$path = $this->getSeedsPath();

		if( !is_dir( $path ) )
		{
			return mkdir( $path, 0755, true );
		}

		return true;
	}

	/**
	 * Execute a Phinx command
	 *
	 * @param string $command Command name (migrate, rollback, status, etc.)
	 * @param array $arguments Command arguments
	 * @return array [exitCode, output]
	 */
	public function execute( string $command, array $arguments = [] ): array
	{
		$application = new PhinxApplication();
		$application->setAutoExit( false );

		// Build input array
		$input = array_merge(
			['command' => $command],
			$arguments,
			['--configuration' => null] // We provide config directly
		);

		$arrayInput = new ArrayInput( $input );
		$output = new BufferedOutput();

		// Set config before running command
		$application->setConfig( $this->getPhinxConfig() );

		$exitCode = $application->run( $arrayInput, $output );

		return [$exitCode, $output->fetch()];
	}
}
