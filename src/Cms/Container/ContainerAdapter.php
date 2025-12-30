<?php

namespace Neuron\Cms\Container;

use Neuron\Patterns\Container\IContainer;
use Psr\Container\ContainerInterface;
use DI\Container as DIContainer;

/**
 * Adapter to make PSR-11 container compatible with Neuron's IContainer interface
 *
 * @package Neuron\Cms\Container
 */
class ContainerAdapter implements IContainer
{
	private ContainerInterface $container;

	public function __construct( ContainerInterface $container )
	{
		$this->container = $container;
	}

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 */
	public function get( string $id )
	{
		return $this->container->get( $id );
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 */
	public function has( string $id ): bool
	{
		return $this->container->has( $id );
	}

	/**
	 * Bind an abstract type to a concrete implementation
	 */
	public function bind( string $abstract, string $concrete ): void
	{
		if( $this->container instanceof DIContainer )
		{
			$this->container->set( $abstract, \DI\autowire( $concrete ) );
		}
	}

	/**
	 * Register a singleton (shared instance) in the container
	 */
	public function singleton( string $abstract, callable $factory ): void
	{
		if( $this->container instanceof DIContainer )
		{
			$this->container->set( $abstract, \DI\factory( $factory ) );
		}
	}

	/**
	 * Resolve and instantiate a class with automatic dependency injection
	 *
	 * @param string $class
	 * @param array<string, mixed> $parameters
	 * @return object
	 */
	public function make( string $class, array $parameters = [] )
	{
		if( $this->container instanceof DIContainer )
		{
			return $this->container->make( $class, $parameters );
		}

		// Fallback: just get from container
		return $this->container->get( $class );
	}

	/**
	 * Register an existing instance as a singleton
	 */
	public function instance( string $abstract, object $instance ): void
	{
		if( $this->container instanceof DIContainer )
		{
			$this->container->set( $abstract, $instance );
		}
	}
}
