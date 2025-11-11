<?php

namespace Neuron\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Maintenance\MaintenanceManager;
use Neuron\Cms\Maintenance\MaintenanceConfig;
use Neuron\Data\Setting\Source\Yaml;

/**
 * CLI command for enabling maintenance mode.
 */
class EnableCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:maintenance:enable';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Enable maintenance mode for the CMS';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'message', 'm', true, 'Custom maintenance message' );
		$this->addOption( 'allow-ip', 'a', true, 'Comma-separated list of allowed IP addresses' );
		$this->addOption( 'retry-after', 'r', true, 'Retry-After header value in seconds' );
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

		// Load configuration
		$config = $this->loadConfiguration( $configPath );

		// Get or determine maintenance parameters
		$message = $this->input->getOption( 'message' );
		if( !$message )
		{
			$message = $config ? $config->getDefaultMessage() : null;
		}

		$allowedIps = $this->parseAllowedIps(
			$this->input->getOption( 'allow-ip' ),
			$config
		);

		$retryAfter = $this->input->getOption( 'retry-after' );
		if( !$retryAfter && $config )
		{
			$retryAfter = $config->getRetryAfter();
		}

		// Confirm action unless forced
		$skipConfirmation = (bool)$this->input->getOption( 'force' );

		if( !$skipConfirmation )
		{
			$this->output->warning( 'This will enable maintenance mode for the site.' );
			$this->output->info( 'Message: ' . $message );
			$this->output->info( 'Allowed IPs: ' . implode( ', ', $allowedIps ) );

			if( $retryAfter )
			{
				$hours = floor( $retryAfter / 3600 );
				$minutes = floor( ($retryAfter % 3600) / 60 );
				$timeStr = $hours > 0
					? "{$hours}h " . ($minutes > 0 ? "{$minutes}m" : '')
					: "{$minutes}m";
				$this->output->info( 'Estimated downtime: ' . $timeStr );
			}

			if( !$this->confirm( 'Enable maintenance mode?' ) )
			{
				$this->output->info( 'Maintenance mode activation cancelled' );
				return 0;
			}
		}
		// Create manager and enable maintenance mode
		$manager = new MaintenanceManager( $basePath );

		$success = $manager->enable(
			$message,
			$allowedIps,
			$retryAfter ? (int)$retryAfter : null,
			get_current_user()
		);

		if( $success )
		{
			$this->output->success( 'Maintenance mode enabled successfully' );
			$this->output->newLine();
			$this->output->info( 'To disable maintenance mode, run:' );
			$this->output->write( '  neuron cms:maintenance:disable' );

			return 0;
		}
		else
		{
			$this->output->error( 'Failed to enable maintenance mode' );
			$this->output->info( 'Check write permissions for: ' . $basePath );

			return 1;
		}
	}

	/**
	 * Parse allowed IPs from option or config
	 *
	 * @param string|null $ipOption
	 * @param MaintenanceConfig|null $config
	 * @return array
	 */
	private function parseAllowedIps( ?string $ipOption, ?MaintenanceConfig $config ): array
	{
		// Check if option was provided (even if empty)
		if( $ipOption !== null )
		{
			// Empty string means no IPs allowed
			if( $ipOption === '' )
			{
				return [];
			}
			return array_map( 'trim', explode( ',', $ipOption ) );
		}

		if( $config )
		{
			return $config->getAllowedIps();
		}

		return ['127.0.0.1', '::1'];
	}

	/**
	 * Load maintenance configuration from config file
	 *
	 * @param string $configPath
	 * @return MaintenanceConfig|null
	 */
	private function loadConfiguration( string $configPath ): ?MaintenanceConfig
	{
		$configFile = $configPath . '/config.yaml';

		if( !file_exists( $configFile ) )
		{
			return null;
		}

		try
		{
			$settings = new Yaml( $configFile );
			return MaintenanceConfig::fromSettings( $settings );
		}
		catch( \Exception $e )
		{
			$this->output->warning( 'Could not load configuration: ' . $e->getMessage() );
			return null;
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
