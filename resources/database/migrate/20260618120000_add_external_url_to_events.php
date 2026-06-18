<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add external_url to events table
 *
 * Some events are managed on a different platform. When an external URL is set,
 * the event links out to that service (e.g. the featured-event widget opens it
 * in a new tab) instead of the internal event page.
 *
 * NOTE: This migration is for EXISTING installations that already ran the
 * original create_events_table migration. New installations will pick up the
 * column from this migration when running migrations in order.
 */
class AddExternalUrlToEvents extends AbstractMigration
{
	/**
	 * Add external_url column to events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		if( !$table->hasColumn( 'external_url' ) )
		{
			$table->addColumn( 'external_url', 'string', [
				'limit' => 500,
				'null' => true,
				'after' => 'featured_image'
			] )
				->update();
		}
	}
}
