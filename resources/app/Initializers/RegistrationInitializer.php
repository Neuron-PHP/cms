<?php

namespace App\Initializers;

use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Auth\Filters\MemberAuthenticationFilter;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;
use Neuron\Patterns\Container\IContainer;

/**
 * Initialize the member registration system
 *
 * Registers the registration services and member authentication filter
 */
class RegistrationInitializer implements IRunnable
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

		if( !$settings || !$settings instanceof \Neuron\Data\Settings\SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping registration initialization" );
			return null;
		}

		// Get Application from Registry
		$app = Registry::getInstance()->get( 'App' );

		if( !$app || !$app instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping registration initialization" );
			return null;
		}

		// Get Container from Registry
		$container = Registry::getInstance()->get( 'Container' );

		if( !$container || !$container instanceof IContainer )
		{
			Log::error( "Container not found in Registry, skipping registration initialization" );
			return null;
		}

		// Check if database is configured
		try
		{
			$settingNames = $settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				// Get services from container
				$authentication = $container->get( Authentication::class );
				$emailVerifier = $container->get( EmailVerifier::class );
				$registrationService = $container->get( RegistrationService::class );

				// Create member authentication filter
				$requireVerification = $settings->get( 'member', 'require_email_verification' ) ?? true;
				$memberFilter = new MemberAuthenticationFilter(
					$authentication,
					'/login',
					$requireVerification
				);

				// Register the member filter with the Router
				$app->getRouter()->registerFilter( 'member', $memberFilter );

				// Store services in Registry for backward compatibility
				Registry::getInstance()->set( 'EmailVerifier', $emailVerifier );
				Registry::getInstance()->set( 'RegistrationService', $registrationService );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to initialize registration system: " . $e->getMessage() );
		}

		return null;
	}
}
