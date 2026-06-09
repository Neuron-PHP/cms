<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create event_registrations table.
 *
 * Stores a row for every visitor/member who registers for a specific event.
 * Anonymous registrations capture name/email directly; when a logged-in member
 * registers, user_id links the registration to their account.
 */
class CreateEventRegistrationsTable extends AbstractMigration
{
	/**
	 * Create event_registrations table
	 */
	public function change()
	{
		$table = $this->table( 'event_registrations', [ 'signed' => false ] );

		$table->addColumn( 'event_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'user_id', 'integer', [ 'signed' => false, 'null' => true ] )
			->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'email', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'notes', 'text', [ 'null' => true ] )
			->addColumn( 'status', 'string', [ 'limit' => 20, 'default' => 'registered', 'null' => false ] )
			->addColumn( 'ip_address', 'string', [ 'limit' => 45, 'null' => true ] )
			->addColumn( 'user_agent', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'event_id' ] )
			->addIndex( [ 'email' ] )
			->addIndex( [ 'user_id' ] )
			->addForeignKey( 'event_id', 'events', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->addForeignKey( 'user_id', 'users', 'id', [ 'delete' => 'SET_NULL', 'update' => 'CASCADE' ] )
			->create();
	}
}
