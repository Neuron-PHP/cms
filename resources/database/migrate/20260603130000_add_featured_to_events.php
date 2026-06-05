<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add featured flag to events table
 *
 * This migration adds support for marking an event as "featured" so it can be
 * surfaced via the [featured-event] shortcode.
 *
 * NOTE: This migration is for EXISTING installations that already ran the
 * original create_events_table migration. New installations will pick up the
 * column from this migration when running migrations in order.
 */
class AddFeaturedToEvents extends AbstractMigration
{
	/**
	 * Add featured column to events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		if( !$table->hasColumn( 'featured' ) )
		{
			$table->addColumn( 'featured', 'boolean', [
				'default' => false,
				'null' => false,
				'after' => 'status'
			] )
				->addIndex( [ 'featured' ] )
				->update();
		}
	}
}
