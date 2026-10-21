<?php
namespace Neuron\Cms;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\IMvcApplication;
use Neuron\Orm\Model;
use Neuron\Cms\Auth\Filters\AdminAuthenticationFilter;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Container\Container;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Patterns\Container\IContainer;
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

	$packageViews = dirname( __DIR__ ) . '/resources/views';

	if( is_dir( $packageViews ) )
	{
		if( class_exists( \Neuron\Mvc\Views\ViewLocator::class ) )
		{
			\Neuron\Mvc\Views\ViewLocator::appendPath( $packageViews );
		}
		else
		{
			$registry = Registry::getInstance();
			$current = $registry->get( RegistryKeys::VIEWS_PATH );
			$paths = is_array( $current ) ? $current : ( is_string( $current ) && $current !== '' ? [ $current ] : [] );

			if( !in_array( $packageViews, $paths, true ) )
			{
				$paths[] = $packageViews;
				$registry->set(
					RegistryKeys::VIEWS_PATH,
					count( $paths ) === 1 ? $paths[0] : $paths
				);
			}
		}
	}

	// Load site configuration into Registry for global access
	// This ensures site name, version, etc. are available even when no Content controller runs
	$settings = $app->getSettingManager();
	$registry = Registry::getInstance();

	// Site name
	$siteName = $settings->get( 'site', 'name' ) ?? 'Neuron CMS';
	$registry->set( RegistryKeys::APP_NAME, $siteName );

	// Site URL
	$siteUrl = $settings->get( 'site', 'url' ) ?? '';
	if( $siteUrl )
	{
		$registry->set( RegistryKeys::APP_RSS_URL, $siteUrl . '/blog/rss' );
	}

	// Application version
	try
	{
		$versionFilePath = $settings->get( 'paths', 'version_file' ) ?? "../.version.json";
		$version = \Neuron\Data\Factories\Version::fromFile( $versionFilePath );
		$registry->set( RegistryKeys::APP_VERSION, 'v' . $version->getAsString() );
	}
	catch( \Exception $e )
	{
		$registry->set( RegistryKeys::APP_VERSION, 'v0.0.0' );
	}

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

		registerAdminFilter( $app, $container );

		try
		{
			$router = $app->getRouter();
			$redirectService = $container->get( \Neuron\Cms\Services\Redirect\RedirectService::class );

			if( method_exists( $router, 'setRedirectResolver' ) )
			{
				$router->setRedirectResolver(
					static function( string $uri ) use ( $redirectService ) {
						return $redirectService->resolveAndApply( $uri );
					}
				);
			}
		}
		catch( \Throwable $e )
		{
			\Neuron\Log\Log::debug( 'Redirect resolver not registered: ' . $e->getMessage() );
		}
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
			Registry::getInstance()->set( RegistryKeys::CONTAINER, $container );

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

/**
 * Register the `admin` route filter the admin controllers depend on.
 *
 * Lives here rather than in the site's AuthInitializer because that file is
 * published once at install and not refreshed by cms:upgrade; registering
 * from the package means every site gets the role check the moment it
 * updates. A site initializer that runs later may re-register `admin` to
 * change the allowed roles (or set `auth.admin_roles` in configuration).
 *
 * The Authentication service is resolved on first use, not at boot, so a
 * request that never touches /admin never builds it.
 *
 * @param Application $app
 * @param IContainer $container
 * @return void
 */
function registerAdminFilter( Application $app, IContainer $container ): void
{
	if( !$container->has( Authentication::class ) )
	{
		\Neuron\Log\Log::debug( 'Authentication service unavailable; admin filter not registered' );
		return;
	}

	$roles = $app->getSettingManager()?->get( 'auth', 'admin_roles' );

	if( is_string( $roles ) )
	{
		$roles = array_filter( array_map( 'trim', explode( ',', $roles ) ) );
	}

	$filter = new AdminAuthenticationFilter(
		static fn() => $container->get( Authentication::class ),
		is_array( $roles ) && $roles !== [] ? array_values( $roles ) : AdminAuthenticationFilter::DEFAULT_ROLES
	);

	$app->getRouter()->registerFilter( 'admin', $filter );
}
