<?php

namespace App\Initializers;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\PasswordResetManager;
use Neuron\Cms\Repositories\DatabasePasswordResetTokenRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

/**
 * Initialize password reset system
 *
 * Registers the PasswordResetManager in the Registry
 */
class PasswordResetInitializer implements IRunnable
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

		if( !$settings || !$settings instanceof SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping password reset initialization" );
			return null;
		}

		// Check if database is configured
		try
		{
			$settingNames = $settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				// Get site configuration
				$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost:8000';
				$siteName = $settings->get( 'site', 'name' ) ?? 'Neuron CMS';

				// Get email configuration (with fallbacks)
				$fromEmail = $settings->get( 'mail', 'from_email' ) ?? 'noreply@localhost';
				$fromName = $settings->get( 'mail', 'from_name' ) ?? $siteName;

				// Get token expiration (default 60 minutes)
				$tokenExpiration = $settings->get( 'auth', 'password_reset_expiration' ) ?? 60;

				// Initialize components
				$tokenRepository = new DatabasePasswordResetTokenRepository( $settings );
				$userRepository = new DatabaseUserRepository( $settings );
				$passwordHasher = new PasswordHasher();

				// Create password reset URL
				$resetUrl = rtrim( $siteUrl, '/' ) . '/reset-password';

				// Create password reset manager
				$resetManager = new PasswordResetManager(
					$tokenRepository,
					$userRepository,
					$passwordHasher,
					$resetUrl,
					$fromEmail,
					$fromName
				);

				// Set token expiration if configured
				$resetManager->setTokenExpirationMinutes( (int)$tokenExpiration );

				// Store PasswordResetManager in Registry for easy access
				Registry::getInstance()->set( 'PasswordResetManager', $resetManager );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to initialize password reset: " . $e->getMessage() );
		}

		return null;
	}
}
