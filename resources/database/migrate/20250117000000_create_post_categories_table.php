<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create post_categories junction table for many-to-many relationship
 */
class CreatePostCategoriesTable extends AbstractMigration
{
	/**
	 * Create post_categories junction table
	 */
	public function change()
	{
		$table = $this->table( 'post_categories', [ 'id' => false, 'primary_key' => [ 'post_id', 'category_id' ] ] );

		$table->addColumn( 'post_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'category_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'post_id' ] )
			->addIndex( [ 'category_id' ] )
			->addForeignKey( 'post_id', 'posts', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->addForeignKey( 'category_id', 'categories', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
