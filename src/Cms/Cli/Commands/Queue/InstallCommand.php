<?php

namespace Neuron\Cms\Cli\Commands\Queue;

use Neuron\Cli\Commands\Command;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;
use Neuron\Patterns\Registry;

/**
 * Install the job queue system
 *
 * Sets up database tables and configuration for the Neuron queue system
 */
class InstallCommand extends Command
{
	private string $_projectPath;

	public function __construct()
	{
		$this->_projectPath = getcwd();
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'queue:install';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Install the job queue system';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addOption( 'force', 'f', false, 'Force installation even if already installed' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $parameters = [] ): int
	{
		$this->output->info( "╔═══════════════════════════════════════╗" );
		$this->output->info( "║  Job Queue Installation               ║" );
		$this->output->info( "╚═══════════════════════════════════════╝" );
		$this->output->write( "\n" );

		// Check if jobs component is available
		if( !class_exists( 'Neuron\\Jobs\\Queue\\QueueManager' ) )
		{
			$this->output->error( "Job queue component not found." );
			$this->output->info( "Please install it first: composer require neuron-php/jobs" );
			return 1;
		}

		$force = $this->input->hasOption( 'force' );

		// Check if already installed
		if( !$force && $this->isAlreadyInstalled() )
		{
			$this->output->warning( "Queue system appears to be already installed." );
			$this->output->info( "  - Migration exists" );
			$this->output->info( "  - Configuration exists" );
			$this->output->write( "\n" );

			if( !$this->input->confirm( "Do you want to continue anyway?", false ) )
			{
				$this->output->info( "Installation cancelled." );
				return 0;
			}
		}

		// Generate queue migration
		$this->output->info( "Generating queue migration..." );

		if( !$this->generateMigration() )
		{
			return 1;
		}

		// Add queue configuration
		$this->output->info( "Adding queue configuration..." );

		if( $this->addQueueConfig() )
		{
			$this->output->success( "Queue configuration added to config.yaml" );
		}
		else
		{
			$this->output->warning( "Could not add queue configuration automatically" );
			$this->output->info( "Please add the following to config/config.yaml:" );
			$this->output->write( "\n" );
			$this->output->write( "queue:\n" );
			$this->output->write( "  driver: database\n" );
			$this->output->write( "  default: default\n" );
			$this->output->write( "  retry_after: 90\n" );
			$this->output->write( "  max_attempts: 3\n" );
			$this->output->write( "  backoff: 0\n" );
			$this->output->write( "\n" );
		}

		// Ask to run migration
		$this->output->write( "\n" );

		if( $this->input->confirm( "Would you like to run the queue migration now?", true ) )
		{
			if( !$this->runMigration() )
			{
				$this->output->error( "Migration failed!" );
				$this->output->info( "You can run it manually with: php neuron db:migrate" );
				return 1;
			}
		}
		else
		{
			$this->output->info( "Remember to run migration with: php neuron db:migrate" );
		}

		// Display success and usage info
		$this->output->write( "\n" );
		$this->output->success( "Job Queue Installation Complete!" );
		$this->output->write( "\n" );
		$this->output->info( "Queue Configuration:" );
		$this->output->info( "  Driver: database" );
		$this->output->info( "  Default Queue: default" );
		$this->output->info( "  Max Attempts: 3" );
		$this->output->info( "  Retry After: 90 seconds" );
		$this->output->write( "\n" );

		$this->output->info( "Start a worker:" );
		$this->output->info( "  php neuron jobs:work" );
		$this->output->write( "\n" );

		$this->output->info( "Dispatch a job:" );
		$this->output->info( "  dispatch(new MyJob(), ['data' => 'value']);" );
		$this->output->write( "\n" );

		$this->output->info( "For more information, see: vendor/neuron-php/jobs/QUEUE.md" );

		return 0;
	}

	/**
	 * Check if queue is already installed
	 */
	private function isAlreadyInstalled(): bool
	{
		$migrationsDir = $this->_projectPath . '/db/migrate';
		$snakeCaseName = $this->camelToSnake( 'CreateQueueTables' );

		// Check for existing migration
		$existingFiles = glob( $migrationsDir . '/*_' . $snakeCaseName . '.php' );

		if( empty( $existingFiles ) )
		{
			return false;
		}

		// Check for queue config
		$configFile = $this->_projectPath . '/config/config.yaml';

		if( !file_exists( $configFile ) )
		{
			return false;
		}

		try
		{
			$yaml = new Yaml( $configFile );
			$settings = new SettingManager( $yaml );
			$driver = $settings->get( 'queue', 'driver' );

			return !empty( $driver );
		}
		catch( \Exception $e )
		{
			return false;
		}
	}

	/**
	 * Generate queue migration
	 */
	private function generateMigration(): bool
	{
		$migrationName = 'CreateQueueTables';
		$snakeCaseName = $this->camelToSnake( $migrationName );
		$migrationsDir = $this->_projectPath . '/db/migrate';

		// Create migrations directory if it doesn't exist
		if( !is_dir( $migrationsDir ) )
		{
			if( !mkdir( $migrationsDir, 0755, true ) )
			{
				$this->output->error( "Failed to create migrations directory!" );
				return false;
			}
		}

		// Check if migration already exists
		$existingFiles = glob( $migrationsDir . '/*_' . $snakeCaseName . '.php' );

		if( !empty( $existingFiles ) )
		{
			$existingFile = basename( $existingFiles[0] );
			$this->output->info( "Queue migration already exists: $existingFile" );
			return true;
		}

		// Create migration
		$timestamp = date( 'YmdHis' );
		$className = $migrationName;
		$fileName = $timestamp . '_' . $snakeCaseName . '.php';
		$filePath = $migrationsDir . '/' . $fileName;

		$template = $this->getMigrationTemplate( $className );

		if( file_put_contents( $filePath, $template ) === false )
		{
			$this->output->error( "Failed to create queue migration!" );
			return false;
		}

		$this->output->success( "Created: db/migrate/$fileName" );
		return true;
	}

	/**
	 * Get migration template
	 */
	private function getMigrationTemplate( string $className ): string
	{
		return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create queue tables for job processing
 */
class $className extends AbstractMigration
{
	/**
	 * Create jobs and failed_jobs tables
	 */
	public function change()
	{
		// Jobs table
		\$jobs = \$this->table( 'jobs', [ 'id' => false, 'primary_key' => [ 'id' ] ] );

		\$jobs->addColumn( 'id', 'string', [ 'limit' => 255 ] )
			->addColumn( 'queue', 'string', [ 'limit' => 255 ] )
			->addColumn( 'payload', 'text' )
			->addColumn( 'attempts', 'integer', [ 'default' => 0 ] )
			->addColumn( 'reserved_at', 'integer', [ 'null' => true ] )
			->addColumn( 'available_at', 'integer' )
			->addColumn( 'created_at', 'integer' )
			->addIndex( [ 'queue' ] )
			->addIndex( [ 'available_at' ] )
			->addIndex( [ 'reserved_at' ] )
			->create();

		// Failed jobs table
		\$failedJobs = \$this->table( 'failed_jobs', [ 'id' => false, 'primary_key' => [ 'id' ] ] );

		\$failedJobs->addColumn( 'id', 'string', [ 'limit' => 255 ] )
			->addColumn( 'queue', 'string', [ 'limit' => 255 ] )
			->addColumn( 'payload', 'text' )
			->addColumn( 'exception', 'text' )
			->addColumn( 'failed_at', 'integer' )
			->addIndex( [ 'queue' ] )
			->addIndex( [ 'failed_at' ] )
			->create();
	}
}

PHP;
	}

	/**
	 * Add queue configuration to config.yaml
	 */
	private function addQueueConfig(): bool
	{
		$configFile = $this->_projectPath . '/config/config.yaml';

		if( !file_exists( $configFile ) )
		{
			return false;
		}

		try
		{
			// Read existing config
			$yaml = new Yaml( $configFile );
			$settings = new SettingManager( $yaml );

			// Check if queue config already exists
			$existingDriver = $settings->get( 'queue', 'driver' );

			if( $existingDriver )
			{
				return true; // Already configured
			}

			// Append queue configuration
			$queueConfig = <<<YAML


# Queue Configuration
queue:
  driver: database
  default: default
  retry_after: 90
  max_attempts: 3
  backoff: 0

YAML;

			file_put_contents( $configFile, $queueConfig, FILE_APPEND );

			return true;
		}
		catch( \Exception $e )
		{
			return false;
		}
	}

	/**
	 * Run the migration
	 */
	private function runMigration(): bool
	{
		$this->output->info( "Running migration..." );
		$this->output->write( "\n" );

		try
		{
			// Get the CLI application from the registry
			$app = Registry::getInstance()->get( 'cli.application' );

			if( !$app )
			{
				$this->output->error( "CLI application not found in registry!" );
				return false;
			}

			// Check if db:migrate command exists
			if( !$app->has( 'db:migrate' ) )
			{
				$this->output->error( "db:migrate command not found!" );
				return false;
			}

			// Get the migrate command class
			$commandClass = $app->getRegistry()->get( 'db:migrate' );

			if( !class_exists( $commandClass ) )
			{
				$this->output->error( "Migrate command class not found: {$commandClass}" );
				return false;
			}

			// Instantiate the migrate command
			$migrateCommand = new $commandClass();

			// Set input and output on the command
			$migrateCommand->setInput( $this->input );
			$migrateCommand->setOutput( $this->output );

			// Configure the command
			$migrateCommand->configure();

			// Execute the migrate command
			$exitCode = $migrateCommand->execute();

			if( $exitCode !== 0 )
			{
				$this->output->error( "Migration failed with exit code: $exitCode" );
				return false;
			}

			$this->output->write( "\n" );
			$this->output->success( "Migration completed successfully!" );

			return true;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error running migration: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Convert CamelCase to snake_case
	 */
	private function camelToSnake( string $input ): string
	{
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
	}
}
