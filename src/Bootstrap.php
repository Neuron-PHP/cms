<?php
namespace Neuron\Cms;

use Neuron\Data\Objects\Version;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Mvc\Application;
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
	$app = \Neuron\Mvc\boot( $configPath );

	// Register CMS exceptions that should bubble up to application-level handlers
	// These exceptions require special handling (redirects, specific error pages)
	Registry::getInstance()->set( 'BubbleExceptions', [
		'Neuron\\Cms\\Exceptions\\UnauthenticatedException',
		'Neuron\\Cms\\Exceptions\\EmailVerificationRequiredException',
		'Neuron\\Cms\\Exceptions\\CsrfValidationException'
	] );

	// Build and register the DI container
	// This must happen before initializers run so they can resolve services
	try
	{
		$container = Container::build( $app->getSettingManager() );

		// Set container on Application so MVC router can use it for controller instantiation
		$app->setContainer( $container );
	}
	catch( \Exception $e )
	{
		error_log( 'Container initialization failed: ' . $e->getMessage() );
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
		error_log( 'CMS ORM initialization warning: ' . $e->getMessage() );
	}

	return $app;
}
