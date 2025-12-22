<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create categories table for blog system
 */
class CreateCategoriesTable extends AbstractMigration
{
	/**
	 * Create categories table
	 */
	public function change()
	{
		$table = $this->table( 'categories', [ 'signed' => false ] );

		$table->addColumn( 'name', 'string', [ 'limit' => 255 ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255 ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();
	}
}
