<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create post_tags junction table for many-to-many relationship
 */
class CreatePostTagsTable extends AbstractMigration
{
	/**
	 * Create post_tags junction table
	 */
	public function change()
	{
		$table = $this->table( 'post_tags', [ 'id' => false, 'primary_key' => [ 'post_id', 'tag_id' ] ] );

		$table->addColumn( 'post_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'tag_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'post_id' ] )
			->addIndex( [ 'tag_id' ] )
			->addForeignKey( 'post_id', 'posts', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->addForeignKey( 'tag_id', 'tags', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
