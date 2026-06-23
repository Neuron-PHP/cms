<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create products table.
 *
 * Backs the storefront catalog ( [product] / [products] shortcodes and the
 * admin Products screen ). Each product is a fixed-price, one-time item that
 * can be added to the cart and purchased through hosted Stripe Checkout. The
 * order itself is recorded in the shared `payments` table ( purpose = "order" )
 * with one `order_items` row per line, so the catalog stays decoupled from the
 * transaction record.
 */
class CreateProductsTable extends AbstractMigration
{
	/**
	 * Create products table
	 */
	public function change()
	{
		$table = $this->table( 'products', [ 'signed' => false ] );

		$table->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'sku', 'string', [ 'limit' => 64, 'null' => true ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'price_cents', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'currency', 'string', [ 'limit' => 8, 'null' => false, 'default' => 'usd' ] )
			->addColumn( 'image_url', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'active', 'boolean', [ 'null' => false, 'default' => true ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'active' ] )
			->addIndex( [ 'sort_order' ] )
			->create();
	}
}
