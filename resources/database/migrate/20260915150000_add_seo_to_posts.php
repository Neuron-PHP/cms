<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add per-post SEO fields (meta title, description, keywords).
 */
class AddSeoToPosts extends AbstractMigration
{
	/**
	 * Add SEO columns to posts
	 */
	public function change()
	{
		$table = $this->table( 'posts' );

		$table->addColumn( 'meta_title', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'meta_description', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'meta_keywords', 'string', [ 'limit' => 512, 'null' => true ] )
			->update();
	}
}
