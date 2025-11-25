<?php

namespace Neuron\Cms\Cli\Commands\Maintenance;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Maintenance\MaintenanceManager;
use DateTime;

/**
 * CLI command for checking maintenance mode status.
 */
class StatusCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:maintenance:status';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Show current maintenance mode status';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'config', 'c', true, 'Path to configuration directory' );
		$this->addOption( 'json', 'j', false, 'Output status in JSON format' );
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

		// Check if maintenance mode is enabled
		$isEnabled = $manager->isEnabled();
		$status = $manager->getStatus();

		// Output in JSON format if requested
		if( $this->input->hasOption( 'json' ) )
		{
			$this->outputJson( $isEnabled, $status );
			return 0;
		}

		// Output in human-readable format
		$this->outputHuman( $isEnabled, $status, $basePath );

		return 0;
	}

	/**
	 * Output status in JSON format
	 *
	 * @param bool $isEnabled
	 * @param array|null $status
	 * @return void
	 */
	private function outputJson( bool $isEnabled, ?array $status ): void
	{
		$output = [
			'enabled' => $isEnabled,
			'status' => $status
		];

		$this->output->write( json_encode( $output, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Output status in human-readable format
	 *
	 * @param bool $isEnabled
	 * @param array|null $status
	 * @param string $basePath
	 * @return void
	 */
	private function outputHuman( bool $isEnabled, ?array $status, string $basePath ): void
	{
		$this->output->title( 'Maintenance Mode Status' );

		if( !$isEnabled )
		{
			$this->output->success( 'Maintenance mode is DISABLED' );
			$this->output->info( 'Site is accessible to all users' );
			$this->output->newLine();
			$this->output->info( 'To enable maintenance mode, run:' );
			$this->output->write( '  neuron cms:maintenance:enable' );
			return;
		}

		// Maintenance mode is enabled
		$this->output->warning( 'Maintenance mode is ENABLED' );
		$this->output->newLine();

		if( $status )
		{
			// Message
			$this->output->write( 'Message:' );
			$this->output->write( '  ' . ($status['message'] ?? 'N/A') );
			$this->output->newLine();

			// Enabled at
			if( isset( $status['enabled_at'] ) )
			{
				$enabledAt = $status['enabled_at'];
				$duration = $this->getTimeSince( $enabledAt );

				$this->output->write( 'Enabled at:' );
				$this->output->write( '  ' . $enabledAt . ' (' . $duration . ' ago)' );
				$this->output->newLine();
			}

			// Enabled by
			if( isset( $status['enabled_by'] ) )
			{
				$this->output->write( 'Enabled by:' );
				$this->output->write( '  ' . $status['enabled_by'] );
				$this->output->newLine();
			}

			// Allowed IPs
			if( isset( $status['allowed_ips'] ) && !empty( $status['allowed_ips'] ) )
			{
				$this->output->write( 'Allowed IPs:' );
				foreach( $status['allowed_ips'] as $ip )
				{
					$this->output->write( '  - ' . $ip );
				}
				$this->output->newLine();
			}

			// Retry-After
			if( isset( $status['retry_after'] ) && $status['retry_after'] )
			{
				$retryAfter = (int)$status['retry_after'];
				$hours = floor( $retryAfter / 3600 );
				$minutes = floor( ($retryAfter % 3600) / 60 );

				$timeStr = '';
				if( $hours > 0 )
				{
					$timeStr = "{$hours} hour" . ($hours > 1 ? 's' : '');
					if( $minutes > 0 )
					{
						$timeStr .= " and {$minutes} minute" . ($minutes > 1 ? 's' : '');
					}
				}
				elseif( $minutes > 0 )
				{
					$timeStr = "{$minutes} minute" . ($minutes > 1 ? 's' : '');
				}
				else
				{
					$timeStr = "{$retryAfter} seconds";
				}

				$this->output->write( 'Estimated downtime:' );
				$this->output->write( '  ' . $timeStr );
				$this->output->newLine();
			}
		}

		// Maintenance file location
		$this->output->info( 'Maintenance file: ' . $basePath . '/.maintenance.json' );
		$this->output->newLine();

		// Instructions
		$this->output->info( 'To disable maintenance mode, run:' );
		$this->output->write( '  neuron cms:maintenance:disable' );
	}

	/**
	 * Calculate time since a given datetime
	 *
	 * @param string $datetime
	 * @return string
	 */
	private function getTimeSince( string $datetime ): string
	{
		try
		{
			$enabledTime = new DateTime( $datetime );
			$now = new DateTime();
			$interval = $now->diff( $enabledTime );

			if( $interval->d > 0 )
			{
				return $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
			}

			if( $interval->h > 0 )
			{
				return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
			}

			if( $interval->i > 0 )
			{
				return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
			}

			return $interval->s . ' second' . ($interval->s !== 1 ? 's' : '');
		}
		catch( \Exception $e )
		{
			return 'unknown';
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
}
