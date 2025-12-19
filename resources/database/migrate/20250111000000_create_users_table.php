<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create users and password_reset_tokens tables
 */
class CreateUsersTable extends AbstractMigration
{
	/**
	 * Create users and password_reset_tokens tables
	 */
	public function change()
	{
		// Create users table
		$usersTable = $this->table( 'users' );

		$usersTable->addColumn( 'username', 'string', [ 'limit' => 255 ] )
			->addColumn( 'email', 'string', [ 'limit' => 255 ] )
			->addColumn( 'password_hash', 'string', [ 'limit' => 255 ] )
			->addColumn( 'role', 'string', [ 'limit' => 50, 'default' => 'subscriber' ] )
			->addColumn( 'status', 'string', [ 'limit' => 50, 'default' => 'active' ] )
			->addColumn( 'email_verified', 'boolean', [ 'default' => false ] )
			->addColumn( 'two_factor_secret', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'remember_token', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'failed_login_attempts', 'integer', [ 'default' => 0 ] )
			->addColumn( 'locked_until', 'timestamp', [ 'null' => true ] )
			->addColumn( 'last_login_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'username' ], [ 'unique' => true ] )
			->addIndex( [ 'email' ], [ 'unique' => true ] )
			->addIndex( [ 'remember_token' ] )
			->addIndex( [ 'status' ] )
			->create();

		// Create password_reset_tokens table
		$tokensTable = $this->table( 'password_reset_tokens' );

		$tokensTable->addColumn( 'email', 'string', [ 'limit' => 255 ] )
			->addColumn( 'token', 'string', [ 'limit' => 64 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'expires_at', 'timestamp', [ 'null' => false ] )
			->addIndex( [ 'email' ] )
			->addIndex( [ 'token' ] )
			->addIndex( [ 'expires_at' ] )
			->create();
	}
}
