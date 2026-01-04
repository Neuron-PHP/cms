<?php

namespace Neuron\Cms\Container;

use Symfony\Component\Yaml\Yaml as YamlParser;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * YAML Definition Loader for DI Container
 *
 * Loads service definitions from YAML configuration files and converts them
 * to PHP-DI definition format. Supports environment-specific configuration
 * files and multiple definition types.
 *
 * @package Neuron\Cms\Container
 */
class YamlDefinitionLoader
{
	private string $_configPath;
	private ?string $_environment;
	private array $_definitions = [];

	/**
	 * Constructor
	 *
	 * @param string $configPath Path to the config directory
	 * @param string|null $environment Environment name (testing, production, etc.)
	 */
	public function __construct( string $configPath, ?string $environment = null )
	{
		$this->_configPath = rtrim( $configPath, '/' );
		$this->_environment = $environment;
	}

	/**
	 * Load service definitions from YAML file(s)
	 *
	 * Loads base services.yaml and optionally environment-specific overrides.
	 *
	 * @return array PHP-DI compatible definitions array
	 * @throws \Exception If configuration file cannot be loaded or parsed
	 */
	public function load(): array
	{
		// Load base services configuration
		$baseFile = $this->_configPath . '/services.yaml';
		$baseDefinitions = $this->loadFile( $baseFile );

		// Load environment-specific overrides if specified
		if( $this->_environment )
		{
			$envFile = $this->_configPath . "/services.{$this->_environment}.yaml";
			if( file_exists( $envFile ) )
			{
				$envDefinitions = $this->loadFile( $envFile );
				$baseDefinitions = array_merge( $baseDefinitions, $envDefinitions );
			}
		}

		// Convert YAML structure to PHP-DI definitions
		$this->_definitions = $this->convertToPhpDiDefinitions( $baseDefinitions );

		return $this->_definitions;
	}

	/**
	 * Load and parse a YAML file
	 *
	 * @param string $file Path to YAML file
	 * @return array Parsed YAML data
	 * @throws \Exception If file doesn't exist or cannot be parsed
	 */
	private function loadFile( string $file ): array
	{
		if( !file_exists( $file ) )
		{
			throw new \Exception( "Service configuration file not found: {$file}" );
		}

		try
		{
			$data = YamlParser::parseFile( $file );
			return $data['services'] ?? [];
		}
		catch( ParseException $e )
		{
			throw new \Exception( "Failed to parse service configuration: {$file} - " . $e->getMessage() );
		}
	}

	/**
	 * Convert YAML service definitions to PHP-DI format
	 *
	 * @param array $services Service definitions from YAML
	 * @return array PHP-DI compatible definitions
	 */
	private function convertToPhpDiDefinitions( array $services ): array
	{
		$definitions = [];

		foreach( $services as $serviceName => $config )
		{
			// Handle simple string definitions (aliases)
			if( is_string( $config ) )
			{
				$definitions[$serviceName] = \DI\get( $config );
				continue;
			}

			// Handle array definitions with type
			if( !is_array( $config ) )
			{
				continue;
			}

			$type = $config['type'] ?? 'autowire';

			switch( $type )
			{
				case 'autowire':
					$definitions[$serviceName] = $this->createAutowireDefinition( $serviceName, $config );
					break;

				case 'create':
					$definitions[$serviceName] = $this->createCreateDefinition( $serviceName, $config );
					break;

				case 'factory':
					$definitions[$serviceName] = $this->createFactoryDefinition( $serviceName, $config );
					break;

				case 'alias':
					$definitions[$serviceName] = $this->createAliasDefinition( $config );
					break;

				case 'instance':
					// Instance type is handled at runtime, not in definitions
					// Will be set via Container::instance() after building
					break;

				case 'value':
					$definitions[$serviceName] = $config['value'] ?? null;
					break;

				default:
					throw new \Exception( "Unknown service definition type '{$type}' for service '{$serviceName}'" );
			}
		}

		return $definitions;
	}

	/**
	 * Create autowire definition
	 *
	 * @param string $serviceName
	 * @param array $config
	 * @return mixed
	 */
	private function createAutowireDefinition( string $serviceName, array $config )
	{
		$definition = \DI\autowire( $serviceName );

		// Support constructor parameters if specified
		if( isset( $config['constructor'] ) && is_array( $config['constructor'] ) )
		{
			$params = $this->resolveParameters( $config['constructor'] );
			$definition = \DI\autowire( $serviceName )->constructor( ...$params );
		}

		return $definition;
	}

	/**
	 * Create create definition
	 *
	 * @param string $serviceName
	 * @param array $config
	 * @return mixed
	 */
	private function createCreateDefinition( string $serviceName, array $config )
	{
		$definition = \DI\create( $serviceName );

		// Support constructor parameters
		if( isset( $config['constructor'] ) && is_array( $config['constructor'] ) )
		{
			$params = $this->resolveParameters( $config['constructor'] );
			$definition = $definition->constructor( ...$params );
		}

		// Support method calls
		if( isset( $config['methods'] ) && is_array( $config['methods'] ) )
		{
			foreach( $config['methods'] as $method => $params )
			{
				$resolvedParams = $this->resolveParameters( $params );
				$definition = $definition->method( $method, ...$resolvedParams );
			}
		}

		return $definition;
	}

	/**
	 * Create factory definition
	 *
	 * @param string $serviceName
	 * @param array $config
	 * @return mixed
	 */
	private function createFactoryDefinition( string $serviceName, array $config )
	{
		// Support factory class with static method
		if( isset( $config['factory_class'] ) && isset( $config['factory_method'] ) )
		{
			$factoryClass = $config['factory_class'];
			$factoryMethod = $config['factory_method'];

			return \DI\factory( [$factoryClass, $factoryMethod] );
		}

		// Support factory class with __invoke
		if( isset( $config['factory_class'] ) )
		{
			$factoryClass = $config['factory_class'];
			return \DI\factory( function( $container ) use ( $factoryClass ) {
				$factory = new $factoryClass();
				return $factory( $container );
			});
		}

		throw new \Exception( "Factory definition for '{$serviceName}' must specify factory_class" );
	}

	/**
	 * Create alias definition (reference to another service)
	 *
	 * @param array $config
	 * @return mixed
	 */
	private function createAliasDefinition( array $config )
	{
		if( !isset( $config['target'] ) )
		{
			throw new \Exception( "Alias definition must specify 'target'" );
		}

		return \DI\get( $config['target'] );
	}

	/**
	 * Resolve parameter references
	 *
	 * Converts string parameters starting with @ to service references
	 *
	 * @param array $parameters
	 * @return array
	 */
	private function resolveParameters( array $parameters ): array
	{
		return array_map( function( $param ) {
			// Service reference (starts with @)
			if( is_string( $param ) && strpos( $param, '@' ) === 0 )
			{
				return \DI\get( substr( $param, 1 ) );
			}
			return $param;
		}, $parameters );
	}

	/**
	 * Get loaded definitions
	 *
	 * @return array
	 */
	public function getDefinitions(): array
	{
		return $this->_definitions;
	}
}
