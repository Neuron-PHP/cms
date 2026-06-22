<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create donations table.
 *
 * Stores every donation initiated through the [donation] shortcode. Fixed
 * columns capture the gateway/transaction state (session, payment intent,
 * subscription, amount, status) while the JSON `payload` column holds the
 * dynamic per-form donor field values, mirroring the contact_submissions
 * design so the schema stays stable regardless of each form's field set.
 *
 * A donation is created with status "pending" before the donor is redirected
 * to the gateway, then flipped to "completed" by the verified webhook.
 */
class CreateDonationsTable extends AbstractMigration
{
	/**
	 * Create donations table
	 */
	public function change()
	{
		$table = $this->table( 'donations', [ 'signed' => false ] );

		$table->addColumn( 'form_key', 'string', [ 'limit' => 64, 'null' => false ] )
			->addColumn( 'provider', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'stripe' ] )
			->addColumn( 'session_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payment_intent_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'subscription_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'amount_cents', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'currency', 'string', [ 'limit' => 8, 'null' => false, 'default' => 'usd' ] )
			->addColumn( 'frequency', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'one_time' ] )
			->addColumn( 'status', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'pending' ] )
			->addColumn( 'donor_name', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'donor_email', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payload', 'text', [ 'null' => false, 'default' => '{}' ] )
			->addColumn( 'ip_address', 'string', [ 'limit' => 45, 'null' => true ] )
			->addColumn( 'user_agent', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'completed_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'form_key' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'session_id' ] )
			->addIndex( [ 'created_at' ] )
			->create();
	}
}
