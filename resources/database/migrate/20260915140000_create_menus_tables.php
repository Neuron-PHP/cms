<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create menus and menu_items tables.
 *
 * Named menus ( Header, Footer ) drive the public navbar and footer.
 * Items may link to a CMS page, a named route, or a custom URL, and
 * may nest one level under a parent item.
 */
class CreateMenusTables extends AbstractMigration
{
	/**
	 * Create menus and menu_items tables
	 */
	public function change()
	{
		$menus = $this->table( 'menus', [ 'signed' => false ] );

		$menus->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'location', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'header' ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'location' ] )
			->create();

		$items = $this->table( 'menu_items', [ 'signed' => false ] );

		$items->addColumn( 'menu_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'parent_id', 'integer', [ 'signed' => false, 'null' => true ] )
			->addColumn( 'label', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'item_type', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'url' ] )
			->addColumn( 'page_id', 'integer', [ 'signed' => false, 'null' => true ] )
			->addColumn( 'route_name', 'string', [ 'limit' => 128, 'null' => true ] )
			->addColumn( 'url', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'target', 'string', [ 'limit' => 16, 'null' => false, 'default' => '_self' ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'is_visible', 'boolean', [ 'null' => false, 'default' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'menu_id' ] )
			->addIndex( [ 'parent_id' ] )
			->addIndex( [ 'sort_order' ] )
			->addForeignKey( 'menu_id', 'menus', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
