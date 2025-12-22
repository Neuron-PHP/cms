<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create tags table for blog system
 */
class CreateTagsTable extends AbstractMigration
{
	/**
	 * Create tags table
	 */
	public function change()
	{
		$table = $this->table( 'tags', [ 'signed' => false ] );

		$table->addColumn( 'name', 'string', [ 'limit' => 255 ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();
	}
}
