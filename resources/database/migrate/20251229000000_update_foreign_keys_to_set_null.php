<?php

use Phinx\Migration\AbstractMigration;

/**
 * Update foreign key constraints to use SET NULL instead of CASCADE
 *
 * This aligns database constraints with DependentStrategy::Nullify in model relationships.
 * When users are deleted, their posts and pages should remain with author_id set to NULL.
 */
class UpdateForeignKeysToSetNull extends AbstractMigration
{
	/**
	 * Migrate Up.
	 */
	public function up()
	{
		// Posts table: Drop CASCADE foreign key, make author_id nullable, add SET_NULL foreign key
		$posts = $this->table( 'posts' );
		$posts->dropForeignKey( 'author_id' )->save();

		$posts->changeColumn( 'author_id', 'integer', [ 'signed' => false, 'null' => true ] )
			->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'SET_NULL', 'update' => 'CASCADE' ] )
			->save();

		// Pages table: Drop CASCADE foreign key, make author_id nullable, add SET_NULL foreign key
		$pages = $this->table( 'pages' );
		$pages->dropForeignKey( 'author_id' )->save();

		$pages->changeColumn( 'author_id', 'integer', [ 'signed' => false, 'null' => true ] )
			->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'SET_NULL', 'update' => 'CASCADE' ] )
			->save();
	}

	/**
	 * Migrate Down.
	 */
	public function down()
	{
		// Posts table: Revert to CASCADE
		$posts = $this->table( 'posts' );
		$posts->dropForeignKey( 'author_id' )->save();

		// Note: We can't reliably change NULL values back to NOT NULL in down(),
		// so we'll leave the column nullable but restore the CASCADE constraint
		$posts->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->save();

		// Pages table: Revert to CASCADE
		$pages = $this->table( 'pages' );
		$pages->dropForeignKey( 'author_id' )->save();

		$pages->addForeignKey( 'author_id', 'users', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ] )
			->save();
	}
}
