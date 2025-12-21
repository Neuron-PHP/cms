<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

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

		$table->addColumn( 'post_id', 'biginteger', [ 'signed' => false, 'limit' => MysqlAdapter::INT_BIG, 'null' => false ] )
			->addColumn( 'tag_id', 'biginteger', [ 'signed' => false, 'limit' => MysqlAdapter::INT_BIG, 'null' => false ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'post_id' ] )
			->addIndex( [ 'tag_id' ] )
			->addForeignKey( 'post_id', 'posts', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->addForeignKey( 'tag_id', 'tags', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
