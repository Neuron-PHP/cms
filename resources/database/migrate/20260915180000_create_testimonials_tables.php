<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create testimonials and testimonial_quotes tables.
 *
 * Named collections ( Success Stories, Homepage Quotes, etc. ) are
 * rendered on pages via the [testimonial slug="success-stories"] shortcode.
 */
class CreateTestimonialsTables extends AbstractMigration
{
	public function change()
	{
		$testimonials = $this->table( 'testimonials', [ 'signed' => false ] );

		$testimonials->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'display', 'string', [ 'limit' => 32, 'null' => false, 'default' => 'cards' ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();

		$quotes = $this->table( 'testimonial_quotes', [ 'signed' => false ] );

		$quotes->addColumn( 'testimonial_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'quote', 'text', [ 'null' => false ] )
			->addColumn( 'attribution', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'role', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'organization', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'image_url', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'testimonial_id' ] )
			->addIndex( [ 'sort_order' ] )
			->addForeignKey( 'testimonial_id', 'testimonials', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
