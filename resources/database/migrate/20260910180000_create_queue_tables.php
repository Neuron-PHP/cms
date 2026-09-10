<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create jobs and failed_jobs tables for the database queue driver.
 *
 * Idempotent: skips a table that already exists so upgrades stay safe
 * on sites that installed the queue earlier via the jobs stub.
 */
class CreateQueueTables extends AbstractMigration
{
	/**
	 * Create queue tables when missing.
	 */
	public function up(): void
	{
		if( !$this->hasTable( 'jobs' ) )
		{
			$jobs = $this->table( 'jobs', [ 'id' => false, 'primary_key' => [ 'id' ] ] );

			$jobs->addColumn( 'id', 'string', [ 'limit' => 255, 'null' => false ] )
				->addColumn( 'queue', 'string', [ 'limit' => 255, 'null' => false ] )
				->addColumn( 'payload', 'text', [ 'null' => false ] )
				->addColumn( 'attempts', 'integer', [ 'default' => 0, 'null' => false ] )
				->addColumn( 'reserved_at', 'integer', [ 'null' => true ] )
				->addColumn( 'available_at', 'integer', [ 'null' => false ] )
				->addColumn( 'created_at', 'integer', [ 'null' => false ] )
				->addIndex( [ 'queue' ] )
				->addIndex( [ 'available_at' ] )
				->addIndex( [ 'reserved_at' ] )
				->create();
		}

		if( !$this->hasTable( 'failed_jobs' ) )
		{
			$failedJobs = $this->table( 'failed_jobs', [ 'id' => false, 'primary_key' => [ 'id' ] ] );

			$failedJobs->addColumn( 'id', 'string', [ 'limit' => 255, 'null' => false ] )
				->addColumn( 'queue', 'string', [ 'limit' => 255, 'null' => false ] )
				->addColumn( 'payload', 'text', [ 'null' => false ] )
				->addColumn( 'exception', 'text', [ 'null' => false ] )
				->addColumn( 'failed_at', 'integer', [ 'null' => false ] )
				->addIndex( [ 'queue' ] )
				->addIndex( [ 'failed_at' ] )
				->create();
		}
	}

	/**
	 * Drop queue tables.
	 */
	public function down(): void
	{
		if( $this->hasTable( 'failed_jobs' ) )
		{
			$this->table( 'failed_jobs' )->drop()->save();
		}

		if( $this->hasTable( 'jobs' ) )
		{
			$this->table( 'jobs' )->drop()->save();
		}
	}
}
