<?php

namespace Neuron\Cms\Container;

use DI\ContainerBuilder;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Neuron\Patterns\Container\IContainer;

/**
 * CMS Dependency Injection Container
 *
 * Builds and configures the PSR-11 compliant container for the CMS.
 * Service definitions are loaded from YAML configuration files.
 *
 * @package Neuron\Cms\Container
 */
class Container
{
	private static ?IContainer $instance = null;

	/**
	 * Build and return the DI container
	 *
	 * Loads service definitions from resources/config/services.yaml
	 * and optionally from environment-specific override files.
	 *
	 * @param SettingManager $settings Application settings
	 * @param string|null $environment Environment name (testing, production, etc.)
	 * @return IContainer
	 * @throws \Exception
	 */
	public static function build( SettingManager $settings, ?string $environment = null ): IContainer
	{
		if( self::$instance !== null )
		{
			return self::$instance;
		}

		$builder = new ContainerBuilder();

		// Enable compilation for production (caching)
		// $builder->enableCompilation( __DIR__ . '/../../../var/cache/container' );

		// Load service definitions from YAML
		$configPath = __DIR__ . '/../../../resources/config';
		$loader = new YamlDefinitionLoader( $configPath, $environment );
		$definitions = $loader->load();

		// Add SettingManager as a concrete instance
		$definitions[SettingManager::class] = $settings;

		// Add all definitions to container builder
		$builder->addDefinitions( $definitions );

		// Build the PSR-11 container
		$psr11Container = $builder->build();

		// Wrap PSR-11 container with Neuron IContainer adapter
		self::$instance = new ContainerAdapter( $psr11Container );

		// Store container in Registry for backward compatibility
		Registry::getInstance()->set( RegistryKeys::CONTAINER, self::$instance );

		return self::$instance;
	}

	/**
	 * Get the container instance
	 *
	 * @return IContainer|null
	 */
	public static function getInstance(): ?IContainer
	{
		return self::$instance;
	}

	/**
	 * Reset the container instance (useful for testing)
	 *
	 * @return void
	 */
	public static function reset(): void
	{
		self::$instance = null;
	}
}
