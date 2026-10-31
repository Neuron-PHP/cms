<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create remember_tokens table
 *
 * "Remember me" tokens move out of users.remember_token, which held a single
 * token per user: signing in on a second browser replaced the first's token,
 * and signing out anywhere cleared it everywhere. One row per browser lets
 * each keep its own token. users.remember_token is left in place; tokens
 * still stored there are moved here the first time they are used.
 */
class CreateRememberTokensTable extends AbstractMigration
{
	/**
	 * Create remember_tokens table
	 */
	public function change()
	{
		$table = $this->table( 'remember_tokens', [ 'signed' => false ] );

		$table->addColumn( 'user_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'token', 'string', [ 'limit' => 64 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'last_used_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'expires_at', 'timestamp', [ 'null' => false ] )
			->addIndex( [ 'user_id' ] )
			->addIndex( [ 'token' ], [ 'unique' => true ] )
			->addIndex( [ 'expires_at' ] )
			->addForeignKey( 'user_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'NO_ACTION' ] )
			->create();
	}
}
