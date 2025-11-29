<?php

use Phinx\Migration\AbstractMigration;

/**
 * Add content_raw column to posts table for Editor.js JSON storage
 */
class AddContentRawToPosts extends AbstractMigration
{
	/**
	 * Add content_raw column
	 */
	public function change()
	{
		$table = $this->table( 'posts' );

		$table->addColumn( 'content_raw', 'text', [
			'null' => false,
			'default' => '{"blocks":[]}',
			'after' => 'body',
			'comment' => 'Editor.js JSON content'
		] )->update();
	}
}
