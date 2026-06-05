<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create contact_submissions table.
 *
 * Stores every contact-form submission. Fixed metadata columns capture
 * routing/delivery info while the JSON `payload` column holds the dynamic,
 * per-form field values so the schema stays stable regardless of how each
 * form's configurable field set changes.
 */
class CreateContactSubmissionsTable extends AbstractMigration
{
	/**
	 * Create contact_submissions table
	 */
	public function change()
	{
		$table = $this->table( 'contact_submissions', [ 'signed' => false ] );

		$table->addColumn( 'form_key', 'string', [ 'limit' => 64, 'null' => false ] )
			->addColumn( 'recipient', 'string', [ 'limit' => 255, 'null' => false ] )
			->addColumn( 'subject', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'reply_to_email', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'reply_to_name', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'payload', 'text', [ 'null' => false, 'default' => '{}' ] )
			->addColumn( 'ip_address', 'string', [ 'limit' => 45, 'null' => true ] )
			->addColumn( 'user_agent', 'string', [ 'limit' => 500, 'null' => true ] )
			->addColumn( 'delivered', 'boolean', [ 'default' => false ] )
			->addColumn( 'delivered_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'form_key' ] )
			->addIndex( [ 'delivered' ] )
			->addIndex( [ 'created_at' ] )
			->create();
	}
}
