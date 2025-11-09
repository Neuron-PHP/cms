<?php

namespace Neuron\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Maintenance\MaintenanceManager;

/**
 * CLI command for disabling maintenance mode.
 */
class DisableCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:maintenance:disable';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Disable maintenance mode for the CMS';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'config', 'c', true, 'Path to configuration directory' );
		$this->addOption( 'force', 'f', false, 'Skip confirmation prompt' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		// Get configuration path
		$configPath = $this->input->getOption( 'config', $this->findConfigPath() );

		if( !$configPath || !is_dir( $configPath ) )
		{
			$this->output->error( 'Configuration directory not found: ' . ($configPath ?: 'none specified') );
			$this->output->info( 'Use --config to specify the configuration directory' );
			return 1;
		}

		// Determine base path
		$basePath = dirname( $configPath );

		// Create manager
		$manager = new MaintenanceManager( $basePath );

		// Check if maintenance mode is currently enabled
		if( !$manager->isEnabled() )
		{
			$this->output->warning( 'Maintenance mode is not currently enabled' );
			return 0;
		}

		// Get current status for display
		$status = $manager->getStatus();

		// Confirm action unless forced
		if( !$this->input->hasOption( 'force' ) )
		{
			$this->output->info( 'Current maintenance mode status:' );
			$this->output->info( 'Message: ' . ($status['message'] ?? 'N/A') );
			$this->output->info( 'Enabled at: ' . ($status['enabled_at'] ?? 'Unknown') );
			$this->output->info( 'Enabled by: ' . ($status['enabled_by'] ?? 'Unknown') );
			$this->output->newLine();

			if( !$this->confirm( 'Disable maintenance mode?' ) )
			{
				$this->output->info( 'Maintenance mode deactivation cancelled' );
				return 0;
			}
		}

		// Disable maintenance mode
		$success = $manager->disable();

		if( $success )
		{
			$this->output->success( 'Maintenance mode disabled successfully' );
			$this->output->info( 'Site is now accessible to all users' );

			return 0;
		}
		else
		{
			$this->output->error( 'Failed to disable maintenance mode' );
			$this->output->info( 'Check write permissions for: ' . $basePath );

			return 1;
		}
	}

	/**
	 * Try to find the configuration directory
	 *
	 * @return string|null
	 */
	private function findConfigPath(): ?string
	{
		$locations = [
			getcwd() . '/config',
			dirname( getcwd() ) . '/config',
			dirname( getcwd(), 2 ) . '/config',
			dirname( __DIR__, 6 ) . '/config',
			dirname( __DIR__, 7 ) . '/config',
		];

		foreach( $locations as $location )
		{
			if( is_dir( $location ) )
			{
				return $location;
			}
		}

		return null;
	}

	/**
	 * Ask for confirmation
	 *
	 * @param string $question
	 * @return bool
	 */
	private function confirm( string $question ): bool
	{
		$this->output->write( $question . ' [y/N] ' );
		$answer = trim( fgets( STDIN ) );
		return strtolower( $answer ) === 'y' || strtolower( $answer ) === 'yes';
	}
}
