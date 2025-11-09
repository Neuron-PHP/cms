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
	 */
	public function run( array $Argv = [] ): mixed
	{
		// Get Settings from Registry
		$Settings = Registry::getInstance()->get( 'Settings' );

		if( !$Settings || !$Settings instanceof SettingManager )
		{
			Log::error( "Settings not found in Registry, skipping password reset initialization" );
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

		// Only initialize if database is configured
		if( !empty( $dbConfig ) )
		{
			try
			{
				// Get site configuration
				$siteUrl = $Settings->get( 'site', 'url' ) ?? 'http://localhost:8000';
				$siteName = $Settings->get( 'site', 'name' ) ?? 'Neuron CMS';

				// Get email configuration (with fallbacks)
				$fromEmail = $Settings->get( 'mail', 'from_email' ) ?? 'noreply@localhost';
				$fromName = $Settings->get( 'mail', 'from_name' ) ?? $siteName;

				// Get token expiration (default 60 minutes)
				$tokenExpiration = $Settings->get( 'auth', 'password_reset_expiration' ) ?? 60;

				// Initialize components
				$tokenRepository = new DatabasePasswordResetTokenRepository( $dbConfig );
				$userRepository = new DatabaseUserRepository( $dbConfig );
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

				Log::debug( "Password reset system initialized" );
			}
			catch( \Exception $e )
			{
				Log::error( "Failed to initialize password reset: " . $e->getMessage() );
			}
		}

		return null;
	}
}
