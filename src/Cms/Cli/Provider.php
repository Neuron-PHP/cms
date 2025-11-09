<?php

namespace Neuron\Cms\Cli;

use Neuron\Cli\Commands\Registry;

/**
 * CLI provider for the CMS component.
 * Registers all CMS-related CLI commands.
 */
class Provider
{
	/**
	 * Register CMS commands with the CLI registry
	 *
	 * @param Registry $registry CLI Registry instance
	 * @return void
	 */
	public static function register( Registry $registry ): void
	{
		// Installation command
		$registry->register(
			'cms:install',
			'Neuron\\Cms\\Cli\\Commands\\Install\\InstallCommand'
		);

		// User management commands
		$registry->register(
			'cms:user:create',
			'Neuron\\Cms\\Cli\\Commands\\User\\CreateCommand'
		);

		$registry->register(
			'cms:user:list',
			'Neuron\\Cms\\Cli\\Commands\\User\\ListCommand'
		);

		$registry->register(
			'cms:user:delete',
			'Neuron\\Cms\\Cli\\Commands\\User\\DeleteCommand'
		);

		// Maintenance mode commands
		$registry->register(
			'cms:maintenance:enable',
			'Neuron\\Cms\\Cli\\Commands\\Maintenance\\EnableCommand'
		);

		$registry->register(
			'cms:maintenance:disable',
			'Neuron\\Cms\\Cli\\Commands\\Maintenance\\DisableCommand'
		);

		$registry->register(
			'cms:maintenance:status',
			'Neuron\\Cms\\Cli\\Commands\\Maintenance\\StatusCommand'
		);

		// Email template generator
		$registry->register(
			'mail:generate',
			'Neuron\\Cms\\Cli\\Commands\\Generate\\EmailCommand'
		);

		// Queue installation
		$registry->register(
			'queue:install',
			'Neuron\\Cms\\Cli\\Commands\\Queue\\InstallCommand'
		);
	}
}
