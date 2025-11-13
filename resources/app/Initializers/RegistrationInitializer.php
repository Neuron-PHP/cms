<?php

namespace App\Initializers;

use Neuron\Cms\Auth\EmailVerificationManager;
use Neuron\Cms\Auth\Filters\MemberAuthenticationFilter;
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Repositories\DatabaseEmailVerificationTokenRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Patterns\IRunnable;

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

		if( !$settings || !$settings instanceof \Neuron\Data\Setting\SettingManager )
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

		// Get AuthManager from Registry (must be initialized first by AuthInitializer)
		$authManager = Registry::getInstance()->get( 'AuthManager' );

		if( !$authManager )
		{
			Log::error( "AuthManager not found in Registry, skipping registration initialization" );
			return null;
		}

		// Check if database is configured
		try
		{
			$settingNames = $settings->getSectionSettingNames( 'database' );

			if( !empty( $settingNames ) )
			{
				// Initialize registration components
				$userRepository = new DatabaseUserRepository( $settings );
				$tokenRepository = new DatabaseEmailVerificationTokenRepository( $settings );
				$passwordHasher = new PasswordHasher();

				// Get base path for email templates
				$basePath = $settings->get( 'system', 'base_path' ) ?? getcwd();

				// Get verification URL from settings
				$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost:8000';
				$verificationPath = $settings->get( 'member', 'verification_url' ) ?? '/verify-email';
				$verificationUrl = rtrim( $siteUrl, '/' ) . '/' . ltrim( $verificationPath, '/' );

				// Create EmailVerificationManager
				$verificationManager = new EmailVerificationManager(
					$tokenRepository,
					$userRepository,
					$settings,
					$basePath,
					$verificationUrl
				);

				// Set token expiration from settings
				$tokenExpiration = $settings->get( 'member', 'verification_token_expiration_minutes' ) ?? 60;
				$verificationManager->setTokenExpirationMinutes( $tokenExpiration );

				// Get event emitter if available
				$emitter = Registry::getInstance()->get( 'EventEmitter' );

				// Create RegistrationService
				$registrationService = new RegistrationService(
					$userRepository,
					$passwordHasher,
					$verificationManager,
					$settings,
					$emitter
				);

				// Create member authentication filter
				$requireVerification = $settings->get( 'member', 'require_email_verification' ) ?? true;
				$memberFilter = new MemberAuthenticationFilter(
					$authManager,
					'/login',
					$requireVerification
				);

				// Register the member filter with the Router
				$app->getRouter()->registerFilter( 'member', $memberFilter );

				// Store services in Registry for easy access
				Registry::getInstance()->set( 'EmailVerificationManager', $verificationManager );
				Registry::getInstance()->set( 'RegistrationService', $registrationService );

				Log::info( "Registration system initialized successfully" );
			}
		}
		catch( \Exception $e )
		{
			Log::error( "Failed to initialize registration system: " . $e->getMessage() );
		}

		return null;
	}
}
