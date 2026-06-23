<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create payments table.
 *
 * Stores every individual charge initiated through the [payment] / [donation]
 * shortcodes: one-time payments AND each recurring subscription invoice ( the
 * initial charge and every renewal each get their own row ). Fixed columns
 * capture the gateway/transaction state ( session, payment intent, invoice,
 * subscription, amount, status ) while the JSON `payload` column holds the
 * dynamic per-form payer field values, mirroring the contact_submissions
 * design so the schema stays stable regardless of each form's field set.
 *
 * `purpose` ( e.g. donation, membership ) tags what the payment is for, so a
 * single engine serves donations, memberships, dues, etc. A payment is created
 * with status "pending" before the payer is redirected to the gateway, then
 * flipped to "completed" by the verified webhook.
 */
class CreatePaymentsTable extends AbstractMigration
{
	/**
	 * Create payments table
	 */
	public function change()
	{
		$table = $this->table( 'payments', [ 'signed' => false ] );

		$table->addColumn( 'purpose', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'donation' ] )
			->addColumn( 'form_key', 'string', [ 'limit' => 64, 'null' => false ] )
			->addColumn( 'provider', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'stripe' ] )
			->addColumn( 'type', 'string', [ 'limit' => 16, 'null' => false, 'default' => 'one_time' ] )
			->addColumn( 'session_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payment_intent_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'invoice_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'subscription_id', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'amount_cents', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'currency', 'string', [ 'limit' => 8, 'null' => false, 'default' => 'usd' ] )
			->addColumn( 'frequency', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'one_time' ] )
			->addColumn( 'status', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'pending' ] )
			->addColumn( 'payer_name', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payer_email', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payload', 'text', [ 'null' => false, 'default' => '{}' ] )
			->addColumn( 'ip_address', 'string', [ 'limit' => 45, 'null' => true ] )
			->addColumn( 'user_agent', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'completed_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'purpose' ] )
			->addIndex( [ 'form_key' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'session_id' ] )
			->addIndex( [ 'subscription_id' ] )
			->addIndex( [ 'created_at' ] )
			->create();
	}
}
