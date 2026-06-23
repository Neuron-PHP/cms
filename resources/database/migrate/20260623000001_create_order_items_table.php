<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create order_items table.
 *
 * An order is a row in the shared `payments` table ( purpose = "order" ); this
 * table holds its individual lines. Each row snapshots the product name, SKU,
 * unit price and quantity at purchase time so the historical order is stable
 * even if the catalog product is later edited or removed.
 */
class CreateOrderItemsTable extends AbstractMigration
{
	/**
	 * Create order_items table
	 */
	public function change()
	{
		$table = $this->table( 'order_items', [ 'signed' => false ] );

		$table->addColumn( 'payment_id', 'integer', [ 'null' => false ] )
			->addColumn( 'product_id', 'integer', [ 'null' => true ] )
			->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'sku', 'string', [ 'limit' => 64, 'null' => true ] )
			->addColumn( 'unit_amount_cents', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'quantity', 'integer', [ 'null' => false, 'default' => 1 ] )
			->addColumn( 'currency', 'string', [ 'limit' => 8, 'null' => false, 'default' => 'usd' ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'payment_id' ] )
			->addIndex( [ 'product_id' ] )
			->create();
	}
}
