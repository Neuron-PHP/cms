<?php

namespace App\Initializers;

use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\Filters\AuthenticationFilter;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

/**
 * Initialize authentication system
 *
 * Registers the auth filter with the Router for protecting routes
 */
class AuthInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 */
	public function run( array $Argv = [] ): mixed
	{
		// Get Settings from Registry
		$Settings = Registry::getInstance()->get( 'Settings' );

		if( !$Settings || !$Settings instanceof \Neuron\Data\Setting\SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping auth initialization" );
			return null;
		}

		// Get Application from Registry
		$App = Registry::getInstance()->get( 'App' );

		if( !$App || !$App instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping auth initialization" );
			return null;
		}

		// Get database configuration
		$dbConfig = [];
		try
		{
			$settingNames = $Settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				foreach( $settingNames as $name )
				{
					$value = $Settings->get( 'database', $name );
					if( $value !== null )
					{
						// Convert string values to appropriate types
						$dbConfig[$name] = ( $name === 'port' ) ? (int)$value : $value;
					}
				}
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to load database config: " . $e->getMessage() );
		}

		// Only register auth filter if database is configured
		if( !empty( $dbConfig ) )
		{
			try
			{
				// Initialize authentication components
				$userRepository = new DatabaseUserRepository( $dbConfig );
				$sessionManager = new SessionManager();
				$passwordHasher = new PasswordHasher();
				$authManager = new AuthManager( $userRepository, $sessionManager, $passwordHasher );

				// Create authentication filter
				$authFilter = new AuthenticationFilter( $authManager, '/login' );

				// Register the auth filter with the Router
				$App->getRouter()->registerFilter( 'auth', $authFilter );

				// Store AuthManager in Registry for easy access
				Registry::getInstance()->set( 'AuthManager', $authManager );
			}
			catch( \Exception $e )
			{
				Log::error( "Failed to register auth filter: " . $e->getMessage() );
			}
		}

		return null;
	}
}
