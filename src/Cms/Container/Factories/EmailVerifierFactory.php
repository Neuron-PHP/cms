<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Repositories\IEmailVerificationTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating EmailVerifier instances
 *
 * @package Neuron\Cms\Container\Factories
 */
class EmailVerifierFactory
{
	/**
	 * Create EmailVerifier instance with configuration
	 *
	 * @param ContainerInterface $container
	 * @return EmailVerifier
	 */
	public function __invoke( ContainerInterface $container ): EmailVerifier
	{
		$tokenRepository = $container->get( IEmailVerificationTokenRepository::class );
		$userRepository = $container->get( IUserRepository::class );
		$settings = $container->get( SettingManager::class );

		// Get base path and verification URL from settings/registry
		$basePath = Registry::getInstance()->get( 'Base.Path' ) ?? getcwd();
		$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost';
		$verificationUrl = rtrim( $siteUrl, '/' ) . '/verify';

		return new EmailVerifier(
			$tokenRepository,
			$userRepository,
			$settings,
			$basePath,
			$verificationUrl
		);
	}
}
