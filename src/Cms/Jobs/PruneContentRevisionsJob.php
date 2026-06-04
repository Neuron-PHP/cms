<?php

namespace Neuron\Cms\Jobs;

use DateTimeImmutable;
use Neuron\Log\Log;
use PDO;
use Throwable;

/**
 * Prunes the content_revisions table.
 *
 * Content revisions are immutable snapshots created on every page/post save and
 * are never deleted by the application, so they grow without bound. This job
 * enforces a retention policy:
 *  - keep_per_content: keep at most this many newest revisions per content item
 *    (default 50; 0 disables count-based pruning)
 *  - max_age_days: delete revisions older than this many days
 *    (default 365; 0 disables age-based pruning)
 *
 * @package Neuron\Cms\Jobs
 */
class PruneContentRevisionsJob extends MaintenanceJob
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'prune_content_revisions';
	}

	/**
	 * Apply the retention policy to content_revisions.
	 *
	 * @param array $argv
	 * @return bool True on success.
	 */
	public function run( array $argv = [] ): mixed
	{
		$keepPerContent = max( 0, $this->intArg( $argv, 'keep_per_content', 50 ) );
		$maxAgeDays     = max( 0, $this->intArg( $argv, 'max_age_days', 365 ) );

		$pdo = $this->getConnection();

		if( $pdo === null )
		{
			return false;
		}

		try
		{
			$deleted = 0;

			if( $maxAgeDays > 0 )
			{
				$deleted += $this->deleteOlderThan( $pdo, $maxAgeDays );
			}

			if( $keepPerContent > 0 )
			{
				$deleted += $this->trimPerContent( $pdo, $keepPerContent );
			}

			Log::info( "{$this->getName()}: removed $deleted content revision(s)." );
		}
		catch( Throwable $exception )
		{
			Log::error( "{$this->getName()}: failed - {$exception->getMessage()}" );
			return false;
		}

		return true;
	}

	/**
	 * Delete revisions older than the given age.
	 *
	 * @param PDO $pdo
	 * @param int $maxAgeDays
	 * @return int Rows deleted.
	 */
	private function deleteOlderThan( PDO $pdo, int $maxAgeDays ): int
	{
		$cutoff = ( new DateTimeImmutable() )
			->modify( "-$maxAgeDays days" )
			->format( 'Y-m-d H:i:s' );

		$stmt = $pdo->prepare( "DELETE FROM content_revisions WHERE created_at < ?" );
		$stmt->execute( [ $cutoff ] );

		return $stmt->rowCount();
	}

	/**
	 * Keep only the newest $keep revisions per content item, deleting the rest.
	 *
	 * Uses portable SQL (no window functions) so it works on SQLite, MySQL and
	 * PostgreSQL.
	 *
	 * @param PDO $pdo
	 * @param int $keep
	 * @return int Rows deleted.
	 */
	private function trimPerContent( PDO $pdo, int $keep ): int
	{
		$groups = $pdo->query(
			"SELECT content_type, content_id
			 FROM content_revisions
			 GROUP BY content_type, content_id
			 HAVING COUNT(*) > $keep"
		)->fetchAll( PDO::FETCH_ASSOC );

		if( !$groups )
		{
			return 0;
		}

		$selectIds = $pdo->prepare(
			"SELECT id FROM content_revisions
			 WHERE content_type = ? AND content_id = ?
			 ORDER BY created_at DESC, id DESC"
		);

		$deleted = 0;

		foreach( $groups as $group )
		{
			$selectIds->execute( [ $group['content_type'], $group['content_id'] ] );
			$ids = $selectIds->fetchAll( PDO::FETCH_COLUMN );

			$toDelete = array_slice( $ids, $keep );

			if( empty( $toDelete ) )
			{
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $toDelete ), '?' ) );
			$deleteStmt = $pdo->prepare( "DELETE FROM content_revisions WHERE id IN ($placeholders)" );
			$deleteStmt->execute( $toDelete );

			$deleted += $deleteStmt->rowCount();
		}

		return $deleted;
	}
}
