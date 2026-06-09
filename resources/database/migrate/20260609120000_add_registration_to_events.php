<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add registration settings to the events table.
 *
 * Adds support for letting visitors register for an event:
 *   - registration_enabled    whether the public registration form is shown
 *   - registration_visibility 'public' (anyone) or 'private' (members only)
 *
 * NOTE: This migration is for EXISTING installations that already ran the
 * original create_events_table migration. New installations pick up the
 * columns from this migration when running migrations in order.
 */
class AddRegistrationToEvents extends AbstractMigration
{
	/**
	 * Add registration columns to events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		if( !$table->hasColumn( 'registration_enabled' ) )
		{
			$table->addColumn( 'registration_enabled', 'boolean', [
				'default' => false,
				'null' => false,
				'after' => 'status'
			] )
				->addIndex( [ 'registration_enabled' ] )
				->update();
		}

		if( !$table->hasColumn( 'registration_visibility' ) )
		{
			$table->addColumn( 'registration_visibility', 'string', [
				'limit' => 20,
				'default' => 'public',
				'null' => false,
				'after' => 'registration_enabled'
			] )
				->update();
		}
	}
}
