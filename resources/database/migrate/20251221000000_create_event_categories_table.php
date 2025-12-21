<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create event_categories table for calendar system
 */
class CreateEventCategoriesTable extends AbstractMigration
{
	/**
	 * Create event_categories table
	 */
	public function change()
	{
		$table = $this->table( 'event_categories' );

		$table->addColumn( 'name', 'string', [ 'limit' => 255 ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255 ] )
			->addColumn( 'color', 'string', [ 'limit' => 7, 'default' => '#3b82f6' ] ) // Hex color code
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();
	}
}
