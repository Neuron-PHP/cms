<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create event_recurrence_exceptions table.
 *
 * Stores excluded occurrence dates (EXDATE) for a recurring master event.
 * A cancelled single occurrence is recorded here; when expanding the master
 * rule any occurrence whose start matches an exception row is skipped.
 */
class CreateEventRecurrenceExceptionsTable extends AbstractMigration
{
	/**
	 * Create event_recurrence_exceptions table
	 */
	public function change()
	{
		$table = $this->table( 'event_recurrence_exceptions', [ 'signed' => false ] );

		$table->addColumn( 'event_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'occurrence_date', 'datetime', [ 'null' => false ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'event_id', 'occurrence_date' ], [ 'unique' => true ] )
			->addForeignKey( 'event_id', 'events', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
