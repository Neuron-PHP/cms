<?php
namespace Neuron\Cms;

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
	// Use MVC boot() to load configuration via SettingManagerFactory
	// This provides comprehensive config loading:
	// 1. neuron.yaml (base configuration)
	// 2. environments/{env}.yaml (environment-specific config if exists)
	// 3. secrets.yml.enc (encrypted secrets if exists)
	// 4. environments/{env}.secrets.yml.enc (environment secrets if exists)
	// 5. Environment variables (highest priority)
	$app = \Neuron\Mvc\boot( $configPath );

	// Build and register CMS DI container
	try
	{
		$container = Container::build( $app->getSettingManager() );

		// Register the Application instance in the container
		// Controllers can depend on either Application or IMvcApplication
		$container->instance( Application::class, $app );
		$container->instance( IMvcApplication::class, $app );

		// Set container on Application so MVC router can use it for controller instantiation
		$app->setContainer( $container );
	}
	catch( \Exception $e )
	{
		\Neuron\Log\Log::error( 'Container initialization failed: ' . $e->getMessage() );

		// Create a minimal fallback container
		try
		{
			$builder = new \DI\ContainerBuilder();
			$builder->addDefinitions([
				SettingManager::class => $app->getSettingManager()
			]);

			$psr11Container = $builder->build();
			$container = new \Neuron\Cms\Container\ContainerAdapter( $psr11Container );

			// Register the minimal container in Registry
			Registry::getInstance()->set( 'Container', $container );

			// Set on app
			$app->setContainer( $container );
		}
		catch( \Exception $containerException )
		{
			\Neuron\Log\Log::error( 'Failed to create fallback container: ' . $containerException->getMessage() );
		}
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
