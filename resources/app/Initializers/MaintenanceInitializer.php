<?php

namespace App\Initializers;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Maintenance\MaintenanceManager;
use Neuron\Cms\Maintenance\MaintenanceFilter;
use Neuron\Cms\Maintenance\MaintenanceConfig;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

/**
 * Initialize the maintenance mode filter
 *
 * Registers the maintenance mode filter with the Router to intercept
 * requests when maintenance mode is enabled.
 */
class MaintenanceInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 * @param array $argv
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( array $argv = [] ): mixed
	{
		// Get Application from Registry
		$app = Registry::getInstance()->get( RegistryKeys::APP );

		if( !$app || !$app instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping maintenance initialization" );
			return null;
		}

		// Get base path for maintenance file (use application base path, not cwd)
		$basePath = $app->getBasePath();

		// Create maintenance manager
		$manager = new MaintenanceManager( $basePath );

		// Get configuration (if available)
		$config = null;
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		if( $settings && $settings instanceof \Neuron\Data\Settings\SettingManager )
		{
			try
			{
				// Get the source from the SettingManager
				$source = $settings->getSource();
				$config = MaintenanceConfig::fromSettings( $source );
			}
			catch( \Exception $e )
			{
				// No maintenance config, use defaults
			}
		}

		// Create maintenance filter
		$filter = new MaintenanceFilter(
			$manager,
			$config ? $config->getCustomView() : null
		);

		// Register and apply filter globally to all routes
		$app->getRouter()->registerFilter( 'maintenance', $filter );
		$app->getRouter()->addFilter( 'maintenance' );

		// Store manager in registry for CLI commands
		Registry::getInstance()->set( RegistryKeys::MAINTENANCE_MANAGER_LEGACY, $manager );

		if( $config )
		{
			Registry::getInstance()->set( RegistryKeys::MAINTENANCE_CONFIG_LEGACY, $config );
		}

		return null;
	}
}
