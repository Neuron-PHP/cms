<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * Create email_verification_tokens table
 */
class CreateEmailVerificationTokensTable extends AbstractMigration
{
	/**
	 * Create email_verification_tokens table
	 */
	public function change()
	{
		$table = $this->table( 'email_verification_tokens', [ 'signed' => false ] );

		$table->addColumn( 'user_id', 'biginteger', [ 'signed' => false, 'limit' => MysqlAdapter::INT_BIG, 'null' => false ] )
			->addColumn( 'token', 'string', [ 'limit' => 64 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'expires_at', 'timestamp', [ 'null' => false ] )
			->addIndex( [ 'user_id' ] )
			->addIndex( [ 'token' ], [ 'unique' => true ] )
			->addIndex( [ 'expires_at' ] )
			->addForeignKey( 'user_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'NO_ACTION' ] )
			->create();
	}
}
