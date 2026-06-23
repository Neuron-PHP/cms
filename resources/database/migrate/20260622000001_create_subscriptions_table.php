<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create subscriptions table.
 *
 * Tracks the lifecycle of a recurring agreement created through a recurring
 * [payment] / [donation] ( e.g. a monthly donation or a membership ). One
 * subscription row owns many `payments` rows ( the initial charge plus every
 * renewal ), linked by the gateway `subscription_id`.
 *
 * The row is created when the checkout that started the subscription completes,
 * its `status` / `current_period_end` are kept current by subscription and
 * invoice webhooks, and it is marked canceled when the agreement ends.
 */
class CreateSubscriptionsTable extends AbstractMigration
{
	/**
	 * Create subscriptions table
	 */
	public function change()
	{
		$table = $this->table( 'subscriptions', [ 'signed' => false ] );

		$table->addColumn( 'purpose', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'donation' ] )
			->addColumn( 'form_key', 'string', [ 'limit' => 64, 'null' => false ] )
			->addColumn( 'provider', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'stripe' ] )
			->addColumn( 'subscription_id', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'status', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'active' ] )
			->addColumn( 'frequency', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'monthly' ] )
			->addColumn( 'amount_cents', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'currency', 'string', [ 'limit' => 8, 'null' => false, 'default' => 'usd' ] )
			->addColumn( 'payer_name', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payer_email', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payload', 'text', [ 'null' => false, 'default' => '{}' ] )
			->addColumn( 'current_period_end', 'timestamp', [ 'null' => true ] )
			->addColumn( 'canceled_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'purpose' ] )
			->addIndex( [ 'form_key' ] )
			->addIndex( [ 'status' ] )
			->addIndex( [ 'subscription_id' ], [ 'unique' => true ] )
			->create();
	}
}
