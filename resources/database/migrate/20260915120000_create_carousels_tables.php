<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create carousels and carousel_slides tables.
 *
 * Named carousels ( Hero, Partners, etc. ) are rendered on pages via the
 * [carousel slug="hero"] shortcode. Slides store a media-library image URL
 * plus optional heading, caption, link, and alt text.
 */
class CreateCarouselsTables extends AbstractMigration
{
	/**
	 * Create carousels and carousel_slides tables
	 */
	public function change()
	{
		$carousels = $this->table( 'carousels', [ 'signed' => false ] );

		$carousels->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'display', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'slider' ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();

		$slides = $this->table( 'carousel_slides', [ 'signed' => false ] );

		$slides->addColumn( 'carousel_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'image_url', 'string', [ 'limit' => 512, 'null' => false ] )
			->addColumn( 'alt_text', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'heading', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'caption', 'text', [ 'null' => true ] )
			->addColumn( 'link_url', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'link_label', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'carousel_id' ] )
			->addIndex( [ 'sort_order' ] )
			->addForeignKey( 'carousel_id', 'carousels', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
