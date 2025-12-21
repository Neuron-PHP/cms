<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

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

		$table->addColumn( 'post_id', 'biginteger', [ 'signed' => false, 'limit' => MysqlAdapter::INT_BIG, 'null' => false ] )
			->addColumn( 'category_id', 'biginteger', [ 'signed' => false, 'limit' => MysqlAdapter::INT_BIG, 'null' => false ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'post_id' ] )
			->addIndex( [ 'category_id' ] )
			->addForeignKey( 'post_id', 'posts', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->addForeignKey( 'category_id', 'categories', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
