<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Settings\SettingManager;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating PasswordHasher instances
 *
 * @package Neuron\Cms\Container\Factories
 */
class PasswordHasherFactory
{
	/**
	 * Create PasswordHasher instance with configuration from settings
	 *
	 * @param ContainerInterface $container
	 * @return PasswordHasher
	 */
	public function __invoke( ContainerInterface $container ): PasswordHasher
	{
		$settings = $container->get( SettingManager::class );
		$hasher = new PasswordHasher();

		try
		{
			$minLength = $settings->get( 'password', 'min_length' );
			if( $minLength )
			{
				$hasher->setMinLength( (int)$minLength );
			}
		}
		catch( \Exception $e )
		{
			// Use defaults
		}

		return $hasher;
	}
}
