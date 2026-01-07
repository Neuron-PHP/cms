<?php
namespace Neuron\Cms;

use Neuron\Data\Objects\Version;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\SettingManagerFactory;
use Neuron\Data\Factories;
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
	// Initialize settings variable to avoid undefined variable error
	$settings = null;
	$container = null;

	// Build and register the DI container BEFORE MVC boot
	// This ensures the container is available to initializers during boot
	try
	{
		// Determine environment
		// Check APP_ENV environment variable (testing, development, production)
		$environment = getenv( 'APP_ENV' ) ?: 'production';

		// Use SettingManagerFactory for comprehensive configuration loading
		// This will automatically load:
		// 1. neuron.yaml (base configuration)
		// 2. Environment-specific config if exists
		// 3. Encrypted secrets from config/secrets.yml.enc
		// 4. Environment-specific encrypted secrets if exist
		// 5. Environment variables (highest priority)
		$settings = SettingManagerFactory::createCustom( [
			[
				'type' => 'yaml',
				'path' => match( $environment ) {
					'testing' => file_exists( "$configPath/neuron.testing.yaml" )
						? "$configPath/neuron.testing.yaml"
						: "$configPath/neuron.yaml",
					default => "$configPath/neuron.yaml"
				},
				'name' => 'config'
			],
			[
				'type' => 'encrypted',
				'path' => "$configPath/secrets.yml.enc",
				'key' => "$configPath/master.key",
				'name' => 'secrets'
			],
			[
				'type' => 'encrypted',
				'path' => "$configPath/secrets/$environment.yml.enc",
				'key' => "$configPath/secrets/$environment.key",
				'name' => "secrets:$environment"
			],
			[
				'type' => 'env',
				'name' => 'environment'
			]
		] );

		// Build container (automatically registers in Registry)
		$container = Container::build( $settings );
	}
	catch( \Exception $e )
	{
		\Neuron\Log\Log::error( 'Container initialization failed: ' . $e->getMessage() );

		// Create a fallback settings with minimal configuration
		// This allows the application to start even if advanced configuration fails
		$yaml = new Yaml( "$configPath/neuron.yaml" );
		$settings = new SettingManager( $yaml );
	}

	// Create MVC application with our configured SettingManager
	// (Don't use MVC boot as it would create its own SettingManager without secrets)
	$basePath = $settings->get( 'system', 'base_path' ) ?: getenv( 'SYSTEM_BASE_PATH' ) ?: '.';
	$version = \Neuron\Data\Factories\Version::fromFile( "$basePath/.version.json" );
	$app = new Application( $version->getAsString(), $settings );

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
