<?php

namespace Neuron\Cms\Jobs;

use Neuron\Log\Log;
use PDO;
use Throwable;

/**
 * Prunes old rows from the queue failed_jobs table.
 *
 * When the database queue driver is in use, failed jobs are retained
 * indefinitely. This job deletes failed jobs older than the retention window.
 * It is a no-op when the queue tables are not present (the database queue is
 * optional and may not be configured for a given installation).
 *
 * The failed_jobs.failed_at column stores a Unix timestamp.
 *
 * Arguments:
 *  - max_age_days: retention window in days (default 30)
 *
 * @package Neuron\Cms\Jobs
 */
class PruneFailedJobsJob extends MaintenanceJob
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'prune_failed_jobs';
	}

	/**
	 * Delete failed jobs older than the retention window.
	 *
	 * @param array $argv
	 * @return bool True on success (including the no-op when no queue tables).
	 */
	public function run( array $argv = [] ): mixed
	{
		$maxAgeDays = max( 1, $this->intArg( $argv, 'max_age_days', 30 ) );

		$pdo = $this->getConnection();

		if( $pdo === null )
		{
			return false;
		}

		if( !$this->tableExists( $pdo ) )
		{
			Log::debug( "{$this->getName()}: failed_jobs table not present; skipping." );
			return true;
		}

		$cutoff = time() - ( $maxAgeDays * 86400 );

		try
		{
			$stmt = $pdo->prepare( "DELETE FROM failed_jobs WHERE failed_at < ?" );
			$stmt->execute( [ $cutoff ] );
			$deleted = $stmt->rowCount();
		}
		catch( Throwable $exception )
		{
			Log::error( "{$this->getName()}: failed - {$exception->getMessage()}" );
			return false;
		}

		Log::info( "{$this->getName()}: removed $deleted failed job(s) older than $maxAgeDays day(s)." );

		return true;
	}

	/**
	 * Determine whether the failed_jobs table exists.
	 *
	 * @param PDO $pdo
	 * @return bool
	 */
	private function tableExists( PDO $pdo ): bool
	{
		try
		{
			$pdo->query( "SELECT 1 FROM failed_jobs LIMIT 1" );
			return true;
		}
		catch( Throwable $exception )
		{
			return false;
		}
	}
}
