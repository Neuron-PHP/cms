<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create pages table for CMS static/dynamic pages
 */
class CreatePagesTable extends AbstractMigration
{
	/**
	 * Create pages table
	 */
	public function change()
	{
		$table = $this->table( 'pages', [ 'signed' => false ] );

		$table->addColumn( 'title', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'content', 'text', [ 'null' => false, 'comment' => 'Editor.js JSON content' ] )
			->addColumn( 'template', 'string', [ 'limit' => 50, 'default' => 'default' ] )
			->addColumn( 'meta_title', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'meta_description', 'text', [ 'null' => true ] )
			->addColumn( 'meta_keywords', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'author_id', 'biginteger', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'status', 'string', [ 'limit' => 20, 'default' => 'draft' ] )
			->addColumn( 'published_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'view_count', 'integer', [ 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'author_id' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'published_at' ] )
			->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
