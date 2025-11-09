<?php

namespace App\Initializers;

use Neuron\Cms\Maintenance\MaintenanceManager;
use Neuron\Cms\Maintenance\MaintenanceFilter;
use Neuron\Cms\Maintenance\MaintenanceConfig;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

/**
 * Initialize maintenance mode filter
 *
 * Registers the maintenance mode filter with the Router to intercept
 * requests when maintenance mode is enabled.
 */
class MaintenanceInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 */
	public function run( array $Argv = [] ): mixed
	{
		// Get Application from Registry
		$App = Registry::getInstance()->get( 'App' );

		if( !$App || !$App instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping maintenance initialization" );
			return null;
		}

		// Get base path for maintenance file (use application base path, not cwd)
		$basePath = $App->getBasePath();

		// Create maintenance manager
		$manager = new MaintenanceManager( $basePath );

		// Get configuration (if available)
		$config = null;
		$Settings = Registry::getInstance()->get( 'Settings' );

		if( $Settings && $Settings instanceof \Neuron\Data\Setting\SettingManager )
		{
			try
			{
				// Get the source from the SettingManager
				$source = $Settings->getSource();
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
		$App->getRouter()->registerFilter( 'maintenance', $filter );
		$App->getRouter()->addFilter( 'maintenance' );

		// Store manager in registry for CLI commands
		Registry::getInstance()->set( 'maintenance.manager', $manager );

		if( $config )
		{
			Registry::getInstance()->set( 'maintenance.config', $config );
		}

		return null;
	}
}
