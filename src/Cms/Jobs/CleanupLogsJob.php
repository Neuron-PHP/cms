<?php

namespace Neuron\Cms\Jobs;

use Neuron\Log\Log;
use Throwable;

/**
 * Deletes old log files from the application log directory.
 *
 * The default file log destination is append-only with no rotation, so log
 * files grow unbounded. This job removes log files whose last-modified time is
 * older than the configured retention window. The active log file is preserved
 * because it is continuously written to and therefore stays within the window.
 *
 * Arguments:
 *  - max_age_days: retention window in days (default 30)
 *
 * @package Neuron\Cms\Jobs
 */
class CleanupLogsJob extends MaintenanceJob
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cleanup_logs';
	}

	/**
	 * Delete log files older than the retention window.
	 *
	 * @param array $argv
	 * @return bool True on success.
	 */
	public function run( array $argv = [] ): mixed
	{
		$maxAgeDays = max( 1, $this->intArg( $argv, 'max_age_days', 30 ) );

		$directory = $this->resolveLogDirectory();

		if( $directory === null || !is_dir( $directory ) )
		{
			Log::warning( "{$this->getName()}: log directory not found; skipping." );
			return false;
		}

		$cutoff = time() - ( $maxAgeDays * 86400 );
		$deleted = 0;

		try
		{
			foreach( glob( $directory . '/*.log*' ) ?: [] as $file )
			{
				if( !is_file( $file ) )
				{
					continue;
				}

				if( filemtime( $file ) >= $cutoff )
				{
					continue;
				}

				if( @unlink( $file ) )
				{
					$deleted++;
				}
				else
				{
					Log::warning( "{$this->getName()}: could not delete $file" );
				}
			}
		}
		catch( Throwable $exception )
		{
			Log::error( "{$this->getName()}: failed - {$exception->getMessage()}" );
			return false;
		}

		Log::info( "{$this->getName()}: removed $deleted log file(s) older than $maxAgeDays day(s)." );

		return true;
	}

	/**
	 * Resolve the directory that holds the application log files.
	 *
	 * @return string|null
	 */
	private function resolveLogDirectory(): ?string
	{
		$settings = $this->getSettings();

		$logFile = $settings?->get( 'logging', 'file' ) ?? 'storage/logs/app.log';

		// Absolute path: use its directory directly.
		if( str_starts_with( $logFile, '/' ) )
		{
			return dirname( $logFile );
		}

		$basePath = $this->getBasePath();
		$relativeDir = dirname( $logFile );

		if( $basePath === null )
		{
			return $relativeDir;
		}

		return $relativeDir === '.' ? $basePath : $basePath . '/' . $relativeDir;
	}
}
