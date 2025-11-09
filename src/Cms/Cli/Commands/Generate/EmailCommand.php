<?php

namespace Neuron\Cms\Cli\Commands\Generate;

use Neuron\Cli\Commands\Command;

/**
 * Generate email template files
 */
class EmailCommand extends Command
{
	private string $_ProjectPath;
	private string $_ComponentPath;

	public function __construct()
	{
		$this->_ProjectPath = getcwd();
		$this->_ComponentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'mail:generate';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Generate a new email template';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		// No additional configuration needed
	}

	/**
	 * Execute the command
	 */
	public function execute( array $Parameters = [] ): int
	{
		// Get template name from first parameter
		$templateName = $Parameters[0] ?? null;

		if( !$templateName )
		{
			$this->output->error( "Please provide a template name" );
			$this->output->info( "Usage: php neuron mail:generate <name>" );
			$this->output->info( "Example: php neuron mail:generate welcome" );
			return 1;
		}

		// Validate template name (should be lowercase with hyphens)
		if( !preg_match( '/^[a-z][a-z0-9-]*$/', $templateName ) )
		{
			$this->output->error( "Template name must be lowercase and contain only letters, numbers, and hyphens" );
			$this->output->error( "Example: welcome, password-reset, order-confirmation" );
			return 1;
		}

		// Create the template file
		if( !$this->createTemplate( $templateName ) )
		{
			return 1;
		}

		$this->output->success( "Email template created successfully!" );
		$this->output->info( "Template: resources/views/emails/{$templateName}.php" );
		$this->output->info( "" );
		$this->output->info( "Usage in code:" );
		$this->output->info( "  email()->to('user@example.com')" );
		$this->output->info( "         ->subject('Welcome!')" );
		$this->output->info( "         ->template('emails/{$templateName}', \$data)" );
		$this->output->info( "         ->send();" );

		return 0;
	}

	/**
	 * Create the template file
	 */
	private function createTemplate( string $name ): bool
	{
		$emailsDir = $this->_ProjectPath . '/resources/views/emails';

		// Create emails directory if it doesn't exist
		if( !is_dir( $emailsDir ) )
		{
			if( !mkdir( $emailsDir, 0755, true ) )
			{
				$this->output->error( "Failed to create emails directory" );
				return false;
			}
		}

		$filePath = $emailsDir . '/' . $name . '.php';

		// Check if file already exists
		if( file_exists( $filePath ) )
		{
			$this->output->error( "Template already exists: resources/views/emails/{$name}.php" );
			return false;
		}

		// Load stub template
		$stubPath = $this->_ComponentPath . '/src/Cms/Cli/Commands/Generate/stubs/email.stub';

		if( !file_exists( $stubPath ) )
		{
			$this->output->error( "Stub template not found: {$stubPath}" );
			return false;
		}

		$content = file_get_contents( $stubPath );

		// Create title from name (e.g., "welcome" -> "Welcome", "password-reset" -> "Password Reset")
		$title = ucwords( str_replace( '-', ' ', $name ) );

		// Replace placeholders
		$replacements = [
			'title' => $title,
			'content' => '<p>Your email content goes here.</p>'
		];

		$content = $this->replacePlaceholders( $content, $replacements );

		// Write the file
		if( file_put_contents( $filePath, $content ) === false )
		{
			$this->output->error( "Failed to create template file" );
			return false;
		}

		return true;
	}

	/**
	 * Replace placeholders in content
	 */
	private function replacePlaceholders( string $content, array $replacements ): string
	{
		foreach( $replacements as $key => $value )
		{
			$content = str_replace( '{{' . $key . '}}', $value ?? '', $content );
		}
		return $content;
	}
}
