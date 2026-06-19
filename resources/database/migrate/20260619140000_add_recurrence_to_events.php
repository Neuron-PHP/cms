<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add recurrence (RFC 5545 RRULE) support to the events table.
 *
 * A recurring event stores its rule in `rrule` (the master). Individual
 * occurrences are expanded on the fly at query time. A modified single
 * occurrence is stored as a child "override" row that points back to its
 * master via `recurrence_parent_id` and identifies the original occurrence it
 * replaces via `recurrence_id`. `recurrence_until` caches the last occurrence
 * date of a bounded rule (NULL for an infinite rule) so range queries can skip
 * masters whose window does not intersect the requested range.
 *
 * NOTE: For EXISTING installations that already ran the original
 * create_events_table migration. New installations pick up the columns from
 * this migration when running migrations in order.
 */
class AddRecurrenceToEvents extends AbstractMigration
{
	/**
	 * Add recurrence columns to events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		if( !$table->hasColumn( 'rrule' ) )
		{
			$table->addColumn( 'rrule', 'text', [
				'null' => true,
				'after' => 'all_day'
			] )->update();
		}

		if( !$table->hasColumn( 'recurrence_parent_id' ) )
		{
			$table->addColumn( 'recurrence_parent_id', 'integer', [
				'signed' => false,
				'null' => true,
				'after' => 'rrule'
			] )
				->addIndex( [ 'recurrence_parent_id' ] )
				->addForeignKey( 'recurrence_parent_id', 'events', 'id', [
					'delete' => 'CASCADE',
					'update' => 'CASCADE'
				] )
				->update();
		}

		if( !$table->hasColumn( 'recurrence_id' ) )
		{
			$table->addColumn( 'recurrence_id', 'datetime', [
				'null' => true,
				'after' => 'recurrence_parent_id'
			] )
				->addIndex( [ 'recurrence_id' ] )
				->update();
		}

		if( !$table->hasColumn( 'recurrence_until' ) )
		{
			$table->addColumn( 'recurrence_until', 'datetime', [
				'null' => true,
				'after' => 'recurrence_id'
			] )
				->addIndex( [ 'recurrence_until' ] )
				->update();
		}
	}
}
