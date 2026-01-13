<?php

namespace App\Initializers;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Auth\Filters\SecurityHeadersFilter;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

/**
 * Initialize security features
 *
 * Registers security filters and configures security headers
 */
class SecurityInitializer implements IRunnable
{
	/**
	 * Run the initializer
	 *
	 * @param array<int, mixed> $argv
	 * @return mixed
	 */
	public function run( array $argv = [] ): mixed
	{
		// Get Application from Registry
		$app = Registry::getInstance()->get( RegistryKeys::APP );

		if( !$app || !$app instanceof \Neuron\Mvc\Application )
		{
			Log::error( "Application not found in Registry, skipping security initialization" );
			return null;
		}

		// Get Settings from Registry for custom configuration
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		// Load custom security header configuration if available
		$config = [];
		if( $settings && $settings instanceof \Neuron\Data\Settings\SettingManager )
		{
			try
			{
				// Allow customization via settings
				// Example: security.csp, security.frame_options, etc.
				$csp = $settings->get( 'security', 'content_security_policy' );
				if( $csp )
				{
					$config['Content-Security-Policy'] = $csp;
				}

				$frameOptions = $settings->get( 'security', 'frame_options' );
				if( $frameOptions )
				{
					$config['X-Frame-Options'] = $frameOptions;
				}

				$referrerPolicy = $settings->get( 'security', 'referrer_policy' );
				if( $referrerPolicy )
				{
					$config['Referrer-Policy'] = $referrerPolicy;
				}
			}
			catch( \Exception $e )
			{
				// Settings not configured, use defaults
				Log::debug( "No custom security settings found, using defaults" );
			}
		}

		// Create and register security headers filter
		$securityHeadersFilter = new SecurityHeadersFilter( $config );
		$app->getRouter()->registerFilter( 'security-headers', $securityHeadersFilter );

		// Optionally register as a global post-filter if the router supports it
		// For now, routes can use filters: ['security-headers'] to opt-in
		// Or we can add it to common base routes

		return null;
	}
}
