<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add occurrence_date to event_registrations.
 *
 * For recurring events, a registration targets a specific occurrence so that
 * capacity and duplicate-email checks are scoped per occurrence. For
 * non-recurring events the column is NULL and behaviour is unchanged.
 *
 * NOTE: For EXISTING installations that already ran the
 * create_event_registrations_table migration. New installations pick up the
 * column from this migration when running migrations in order.
 */
class AddOccurrenceDateToEventRegistrations extends AbstractMigration
{
	/**
	 * Add occurrence_date column to event_registrations table
	 */
	public function change()
	{
		$table = $this->table( 'event_registrations' );

		if( !$table->hasColumn( 'occurrence_date' ) )
		{
			$table->addColumn( 'occurrence_date', 'datetime', [
				'null' => true,
				'after' => 'event_id'
			] )
				->addIndex( [ 'event_id', 'occurrence_date' ] )
				->update();
		}
	}
}
