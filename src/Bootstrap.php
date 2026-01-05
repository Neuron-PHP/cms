<?php
namespace Neuron\Cms;

use Neuron\Data\Objects\Version;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\IMvcApplication;
use Neuron\Orm\Model;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Container\Container;
use Neuron\Patterns\Registry;

// Load authentication helper functions
require_once __DIR__ . '/Cms/Auth/helpers.php';

/**
 * CMS Bootstrap Module for the Neuron Framework
 *
 * This module provides bootstrap functionality for initializing Neuron CMS
 * applications. It serves as the entry point for CMS-specific configuration
 * and setup, extending the base MVC application with content management
 * capabilities.
 *
 * @package Neuron\Cms
 */

/**
 * Bootstrap and initialize a Neuron CMS application.
 *
 * This function initializes a complete CMS application with all necessary
 * components including routing, content management, blog functionality,
 * and site configuration. It delegates to the MVC boot function but
 * provides CMS-specific context and naming.
 *
 * Additionally initializes the ORM with the database connection from settings.
 *
 * @param string $configPath Path to the CMS configuration directory
 * @return Application Fully configured CMS application instance
 * @throws \Exception If configuration loading or application initialization fails
 */

function boot( string $configPath ) : Application
{
	// Build and register the DI container BEFORE MVC boot
	// This ensures the container is available to initializers during boot
	try
	{
		// Determine which configuration file to load based on environment
		// Check APP_ENV environment variable (testing, development, production)
		$environment = getenv( 'APP_ENV' ) ?: 'production';
		$configFile = match( $environment ) {
			'testing' => 'neuron.testing.yaml',
			'development' => 'neuron.yaml',
			'production' => 'neuron.yaml',
			default => 'neuron.yaml'
		};

		// Fallback to neuron.yaml if environment-specific file doesn't exist
		$configFilePath = "$configPath/$configFile";
		if( !file_exists( $configFilePath ) )
		{
			$configFilePath = "$configPath/neuron.yaml";
		}

		// Create SettingManager from config file
		$yaml = new Yaml( $configFilePath );
		$settings = new SettingManager( $yaml );

		// Build container (automatically registers in Registry)
		$container = Container::build( $settings );
	}
	catch( \Exception $e )
	{
		\Neuron\Log\Log::error( 'Container initialization failed: ' . $e->getMessage() );
	}

	// Boot MVC application (initializers can now access Container from Registry)
	$app = \Neuron\Mvc\boot( $configPath );

	// Update container with Application instance and set on app
	if( isset( $container ) )
	{
		// Register the Application instance in the container
		// Controllers can depend on either Application or IMvcApplication
		$container->instance( Application::class, $app );
		$container->instance( IMvcApplication::class, $app );

		// Set container on Application so MVC router can use it for controller instantiation
		$app->setContainer( $container );
	}

	// Initialize ORM with PDO connection from settings
	try
	{
		$pdo = ConnectionFactory::createFromSettings( $app->getSettingManager() );
		Model::setPdo( $pdo );
	}
	catch( \Exception $e )
	{
		// If database configuration is missing or invalid, log but don't fail
		// This allows the application to start even without database
		\Neuron\Log\Log::error( 'CMS ORM initialization warning: ' . $e->getMessage() );
	}

	return $app;
}
