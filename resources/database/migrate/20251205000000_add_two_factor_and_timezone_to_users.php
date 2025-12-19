<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add two_factor_recovery_codes and timezone columns to users table
 *
 * This migration adds support for:
 * - Two-factor authentication recovery codes (JSON-encoded array)
 * - User timezone preference
 *
 * NOTE: This migration is for EXISTING installations that already ran
 * the original create_users_table migration. New installations will get
 * these columns from the updated create_users_table migration.
 */
class AddTwoFactorAndTimezoneToUsers extends AbstractMigration
{
	/**
	 * Add columns to users table
	 */
	public function change()
	{
		$table = $this->table( 'users' );

		// Check if columns exist before adding (in case running on new installation)
		if( !$table->hasColumn( 'two_factor_recovery_codes' ) )
		{
			$table->addColumn( 'two_factor_recovery_codes', 'text', [
				'null' => true,
				'comment' => 'JSON-encoded recovery codes for 2FA',
				'after' => 'two_factor_secret'
			]);
		}

		if( !$table->hasColumn( 'timezone' ) )
		{
			$table->addColumn( 'timezone', 'string', [
				'limit' => 50,
				'default' => 'UTC',
				'null' => false,
				'after' => 'last_login_at'
			]);
		}

		$table->update();
	}
}
