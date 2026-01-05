<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Data\Settings\SettingManager;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating RegistrationService instances
 *
 * @package Neuron\Cms\Container\Factories
 */
class RegistrationServiceFactory
{
	/**
	 * Create RegistrationService instance
	 *
	 * @param ContainerInterface $container
	 * @return RegistrationService
	 */
	public function __invoke( ContainerInterface $container ): RegistrationService
	{
		$userRepository = $container->get( IUserRepository::class );
		$emailVerifier = $container->get( EmailVerifier::class );
		$passwordHasher = $container->get( PasswordHasher::class );
		$settings = $container->get( SettingManager::class );

		return new RegistrationService(
			$userRepository,
			$passwordHasher,
			$emailVerifier,
			$settings
		);
	}
}
