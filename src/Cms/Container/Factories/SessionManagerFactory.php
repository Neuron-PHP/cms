<?php

namespace Neuron\Cms\Container\Factories;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Config\AuthConfig;
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
		$config = AuthConfig::sessionConfig( $settings instanceof SettingManager ? $settings : null );

		return new SessionManager( $config );
	}
}
