<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create teams and team_members tables.
 *
 * Named teams ( Staff, Board, etc. ) are rendered on pages via the
 * [team slug="board"] shortcode. Members store an optional media-library
 * image URL, title, bio, and a contact value that may be an email or URL.
 */
class CreateTeamsTables extends AbstractMigration
{
	/**
	 * Create teams and team_members tables
	 */
	public function change()
	{
		$teams = $this->table( 'teams', [ 'signed' => false ] );

		$teams->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'slug', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'description', 'text', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'slug' ], [ 'unique' => true ] )
			->addIndex( [ 'name' ] )
			->create();

		$members = $this->table( 'team_members', [ 'signed' => false ] );

		$members->addColumn( 'team_id', 'integer', [ 'signed' => false, 'null' => false ] )
			->addColumn( 'name', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'title', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'bio', 'text', [ 'null' => true ] )
			->addColumn( 'contact', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'image_url', 'string', [ 'limit' => 512, 'null' => true ] )
			->addColumn( 'sort_order', 'integer', [ 'null' => false, 'default' => 0 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'null' => true ] )
			->addIndex( [ 'team_id' ] )
			->addIndex( [ 'sort_order' ] )
			->addForeignKey( 'team_id', 'teams', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->create();
	}
}
