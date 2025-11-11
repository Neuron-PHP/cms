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
	 * @param array $argv
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( array $argv = [] ): mixed
	{
		// Get Settings from Registry
		$settings = Registry::getInstance()->get( 'Settings' );

		if( !$settings || !$settings instanceof \Neuron\Data\Setting\SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping auth initialization" );
			return null;
		}

		// Get Application from Registry
		$app = Registry::getInstance()->get( 'App' );

		if( !$app || !$app instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping auth initialization" );
			return null;
		}

		// Check if database is configured
		try
		{
			$settingNames = $settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				// Initialize authentication components
				$userRepository = new DatabaseUserRepository( $settings );
				$sessionManager = new SessionManager();
				$passwordHasher = new PasswordHasher();
				$authManager = new AuthManager( $userRepository, $sessionManager, $passwordHasher );

				// Create authentication filter
				$authFilter = new AuthenticationFilter( $authManager, '/login' );

				// Register the auth filter with the Router
				$app->getRouter()->registerFilter( 'auth', $authFilter );

				// Store AuthManager in Registry for easy access
				Registry::getInstance()->set( 'AuthManager', $authManager );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to register auth filter: " . $e->getMessage() );
		}

		return null;
	}
}
