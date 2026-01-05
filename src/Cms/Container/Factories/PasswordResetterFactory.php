<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Services\Auth\PasswordResetter;
use Neuron\Cms\Repositories\IPasswordResetTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating PasswordResetter instances
 *
 * @package Neuron\Cms\Container\Factories
 */
class PasswordResetterFactory
{
	/**
	 * Create PasswordResetter instance with configuration
	 *
	 * @param ContainerInterface $container
	 * @return PasswordResetter
	 */
	public function __invoke( ContainerInterface $container ): PasswordResetter
	{
		$tokenRepository = $container->get( IPasswordResetTokenRepository::class );
		$userRepository = $container->get( IUserRepository::class );
		$passwordHasher = $container->get( PasswordHasher::class );
		$settings = $container->get( SettingManager::class );

		// Get base path and site URL from settings
		$basePath = Registry::getInstance()->get( 'Base.Path' ) ?? getcwd();
		$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost';
		$resetUrl = rtrim( $siteUrl, '/' ) . '/reset-password';

		$passwordResetter = new PasswordResetter(
			$tokenRepository,
			$userRepository,
			$passwordHasher,
			$settings,
			$basePath,
			$resetUrl
		);

		// Set token expiration if configured
		try
		{
			$tokenExpiration = $settings->get( 'password_reset', 'token_expiration' );
			if( $tokenExpiration )
			{
				$passwordResetter->setTokenExpirationMinutes( (int)$tokenExpiration );
			}
		}
		catch( \Exception $e )
		{
			// Use default expiration
		}

		return $passwordResetter;
	}
}
