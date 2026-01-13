<?php

namespace App\Initializers;

use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Auth\Filters\AuthenticationFilter;
use Neuron\Cms\Auth\Filters\CsrfFilter;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Container\IContainer;

/**
 * Initialize the authentication system
 *
 * Registers the auth filter with the Router for protecting routes
 */
class AuthInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 * @param array $argv
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( array $argv = [] ): mixed
	{
		// Get Settings from Registry
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		if( !$settings || !$settings instanceof \Neuron\Data\Settings\SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping auth initialization" );
			return null;
		}

		// Get Application from Registry
		$app = Registry::getInstance()->get( RegistryKeys::APP );

		if( !$app || !$app instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping auth initialization" );
			return null;
		}

		// Get Container from Registry
		$container = Registry::getInstance()->get( RegistryKeys::CONTAINER );

		if( !$container || !$container instanceof IContainer )
		{
			Log::error( "Container not found in Registry, skipping auth initialization" );
			return null;
		}

		// Check if database is configured
		try
		{
			$settingNames = $settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				// Get services from container
				// Check if services exist before trying to get them (for fallback container scenarios)
				if( !$container->has( Authentication::class ) ||
					!$container->has( CsrfToken::class ) )
				{
					Log::warning( "Auth services not available in container, skipping auth filter registration" );
					return null;
				}

				$authentication = $container->get( Authentication::class );
				$csrfToken = $container->get( CsrfToken::class );

				// Create filters
				$authFilter = new AuthenticationFilter( $authentication, '/login' );
				$csrfFilter = new CsrfFilter( $csrfToken );

				// Register filters with the Router
				// Note: Routes can now combine multiple filters using filters: [auth, csrf]
				$app->getRouter()->registerFilter( 'auth', $authFilter );
				$app->getRouter()->registerFilter( 'csrf', $csrfFilter );

				// Store services in Registry for backward compatibility
				Registry::getInstance()->set( RegistryKeys::AUTHENTICATION_LEGACY, $authentication );
				Registry::getInstance()->set( RegistryKeys::CSRF_TOKEN_LEGACY, $csrfToken );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to register auth filter: " . $e->getMessage() );
		}

		return null;
	}
}
