<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create faqs and faq_items tables.
 *
 * Named FAQ groups are rendered on pages via [faq slug="general"].
 */
class CreateFaqsTables extends AbstractMigration
{
	public function change()
	{
		$faqs = $this->table( 'faqs', [ 'signed' => false ] );

		$faqs->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();

		$items = $this->table( 'faq_items', [ 'signed' => false ] );

		$items->addColumn( 'faq_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'question', 'string', [ 'limit' => 512, 'null' => false ] )
			->addColumn( 'answer', 'text', [ 'null' => false ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'faq_id' ] )
			->addIndex( [ 'sort_order' ] )
			->addForeignKey( 'faq_id', 'faqs', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
