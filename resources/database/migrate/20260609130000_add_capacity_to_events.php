<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add registration capacity to the events table.
 *
 * Adds a nullable `capacity` column that caps the number of registrations an
 * event accepts. NULL (or a value <= 0) means unlimited.
 *
 * NOTE: This migration is for EXISTING installations that already ran the
 * original create_events_table migration. New installations pick up the
 * column from this migration when running migrations in order.
 */
class AddCapacityToEvents extends AbstractMigration
{
	/**
	 * Add capacity column to events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		if( !$table->hasColumn( 'capacity' ) )
		{
			$table->addColumn( 'capacity', 'integer', [
				'null' => true,
				'default' => null,
				'after' => 'registration_visibility'
			] )
				->update();
		}
	}
}
