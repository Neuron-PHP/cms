<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create posts table for blog system
 */
class CreatePostsTable extends AbstractMigration
{
	/**
	 * Create posts table
	 */
	public function change()
	{
		$table = $this->table( 'posts' );

		$table->addColumn( 'title', 'string', [ 'limit' => 255 ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255 ] )
			->addColumn( 'body', 'text', [ 'null' => false ] )
			->addColumn( 'excerpt', 'text', [ 'null' => true ] )
			->addColumn( 'featured_image', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'author_id', 'integer', [ 'null' => false ] )
			->addColumn( 'status', 'string', [ 'limit' => 20, 'default' => 'draft' ] )
			->addColumn( 'published_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'view_count', 'integer', [ 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'author_id' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'published_at' ] )
			->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
