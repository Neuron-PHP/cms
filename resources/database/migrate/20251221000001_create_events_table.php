<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create events table for calendar system
 */
class CreateEventsTable extends AbstractMigration
{
	/**
	 * Create events table
	 */
	public function change()
	{
		$table = $this->table( 'events' );

		$table->addColumn( 'title', 'string', [ 'limit' => 255 ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255 ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'content_raw', 'text', [ 'null' => false, 'default' => '{"blocks":[]}' ] ) // JSON for Editor.js
			->addColumn( 'location', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'start_date', 'datetime', [ 'null' => false ] )
			->addColumn( 'end_date', 'datetime', [ 'null' => true ] )
			->addColumn( 'all_day', 'boolean', [ 'default' => false ] )
			->addColumn( 'category_id', 'integer', [ 'null' => true ] )
			->addColumn( 'status', 'string', [ 'limit' => 20, 'default' => 'draft' ] )
			->addColumn( 'featured_image', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'organizer', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'contact_email', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'contact_phone', 'string', [ 'limit' => 50, 'null' => true ] )
			->addColumn( 'created_by', 'integer', [ 'null' => false ] )
			->addColumn( 'view_count', 'integer', [ 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'start_date' ] )
			->addIndex( [ 'end_date' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'category_id' ] )
			->addIndex( [ 'created_by' ] )
			->addForeignKey( 'category_id', 'event_categories', 'id', [ 'delete' => 'SET_NULL', 'update' => 'CASCADE' ] )
			->addForeignKey( 'created_by', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
