<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create redirects table for admin-managed HTTP 301/302 rules.
 */
class CreateRedirectsTable extends AbstractMigration
{
	public function change()
	{
		$table = $this->table( 'redirects', [ 'signed' => false ] );

		$table->addColumn( 'from_path', 'string', [ 'limit' => 512, 'null' => false ] )
			->addColumn( 'to_url', 'string', [ 'limit' => 2048, 'null' => false ] )
			->addColumn( 'status_code', 'integer', [ 'limit' => 3, 'null' => false, 'default' => 301 ] )
			->addColumn( 'is_active', 'boolean', [ 'null' => false, 'default' => true ] )
			->addColumn( 'preserve_query', 'boolean', [ 'null' => false, 'default' => true ] )
			->addColumn( 'notes', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'from_path' ], [ 'unique' => true ] )
			->addIndex( [ 'is_active' ] )
			->create();
	}
}
