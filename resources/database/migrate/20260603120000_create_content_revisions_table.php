<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create content_revisions table.
 *
 * Stores a snapshot of a page or post each time it is created, updated or
 * restored, along with who made the change and when. Shared across content
 * types via the content_type / content_id pair.
 */
class CreateContentRevisionsTable extends AbstractMigration
{
	public function change()
	{
		$table = $this->table( 'content_revisions', [ 'signed' => false ] );

		$table->addColumn( 'content_type', 'string', [ 'limit' => 20, 'null' => false, 'comment' => 'page or post' ] )
			->addColumn( 'content_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'title', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'status', 'string', [ 'limit' => 20, 'default' => 'draft' ] )
			->addColumn( 'action', 'string', [ 'limit' => 20, 'default' => 'updated', 'comment' => 'created, updated or restored' ] )
			->addColumn( 'snapshot', 'text', [ 'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG, 'null' => false, 'comment' => 'Full JSON snapshot of the content row' ] )
			->addColumn( 'edited_by', 'integer', [ 'signed' => false, 'null' => true ] )
			->addColumn( 'edited_by_name', 'string', [ 'limit' => 150, 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'content_type', 'content_id' ] )
			->addIndex( [ 'created_at' ] )
			->addForeignKey( 'edited_by', 'users', 'id', [ 'delete' => 'SET_NULL', 'update' => 'CASCADE' ] )
			->create();
	}
}
