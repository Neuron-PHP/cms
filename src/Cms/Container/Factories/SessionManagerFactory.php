<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Settings\SettingManager;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating SessionManager instances
 *
 * @package Neuron\Cms\Container\Factories
 */
class SessionManagerFactory
{
	/**
	 * Create SessionManager instance with configuration from settings
	 *
	 * @param ContainerInterface $container
	 * @return SessionManager
	 */
	public function __invoke( ContainerInterface $container ): SessionManager
	{
		$settings = $container->get( SettingManager::class );
		$config = [];

		try
		{
			$lifetime = $settings->get( 'session', 'lifetime' );
			if( $lifetime )
			{
				$config['lifetime'] = (int)$lifetime;
			}
		}
		catch( \Exception $e )
		{
			// Use defaults if settings not found
		}

		return new SessionManager( $config );
	}
}
