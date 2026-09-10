<?php

namespace Neuron\Cms\Cli\Commands\Queue;

use Neuron\Cli\Commands\Command;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Patterns\Registry;

/**
 * Install the database queue tables.
 *
 * Copies the CMS queue migration into the project if needed, patches the
 * older jobs stub (nullable primary keys fail on MySQL), then runs
 * db:migrate. Available in production — unlike scaffolding's queue:install.
 */
class InstallCommand extends Command
{
	private string $_projectPath;
	private string $_componentPath;

	public function __construct( ?string $projectPath = null, ?string $componentPath = null )
	{
		$this->_projectPath = $projectPath ?? getcwd();
		$this->_componentPath = $componentPath ?? dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
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
		return 'Install the job queue database tables';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addOption( 'skip-migrate', null, false, 'Copy or patch the migration without running it' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( array $parameters = [] ): int
	{
		$this->output->info( 'Installing job queue tables...' );

		$migration = $this->ensureMigration();

		if( $migration === null )
		{
			return 1;
		}

		$this->output->success( 'Migration ready: ' . basename( $migration ) );

		if( $this->input->hasOption( 'skip-migrate' ) )
		{
			$this->output->info( 'Skipped migrate. Run: ./vendor/bin/neuron db:migrate' );
			return 0;
		}

		if( !$this->runMigration() )
		{
			return 1;
		}

		$this->output->info( 'Start a worker with: ./vendor/bin/neuron jobs:work' );
		$this->output->info( 'Or both scheduler and worker: ./vendor/bin/neuron jobs:run' );

		return 0;
	}

	/**
	 * Ensure db/migrate contains a MySQL-safe create_queue_tables migration.
	 *
	 * @return string|null Absolute path to the migration file
	 */
	public function ensureMigration(): ?string
	{
		$migrationsDir = $this->_projectPath . '/db/migrate';

		if( !is_dir( $migrationsDir ) && !mkdir( $migrationsDir, 0755, true ) )
		{
			$this->output->error( 'Failed to create db/migrate' );
			return null;
		}

		$existing = glob( $migrationsDir . '/*_create_queue_tables.php' );

		if( !empty( $existing ) )
		{
			$path = $existing[0];

			if( !$this->isMysqlSafe( $path ) && !$this->patchMigration( $path ) )
			{
				$this->output->error( 'Could not patch existing queue migration for MySQL.' );
				return null;
			}

			return $path;
		}

		$source = $this->_componentPath . '/resources/database/migrate/20260910180000_create_queue_tables.php';

		if( !is_file( $source ) )
		{
			$this->output->error( 'CMS queue migration not found at: ' . $source );
			return null;
		}

		$dest = $migrationsDir . '/20260910180000_create_queue_tables.php';

		if( !copy( $source, $dest ) )
		{
			$this->output->error( 'Failed to copy queue migration.' );
			return null;
		}

		return $dest;
	}

	/**
	 * Primary key id columns must be NOT NULL for MySQL.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isMysqlSafe( string $path ): bool
	{
		$contents = file_get_contents( $path );

		if( $contents === false )
		{
			return false;
		}

		return str_contains( $contents, "'null' => false" )
			&& str_contains( $contents, "addColumn( 'id'" );
	}

	/**
	 * Patch the jobs stub so string primary keys are NOT NULL.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function patchMigration( string $path ): bool
	{
		$contents = file_get_contents( $path );

		if( $contents === false )
		{
			return false;
		}

		$patched = str_replace(
			"addColumn( 'id', 'string', [ 'limit' => 255 ] )",
			"addColumn( 'id', 'string', [ 'limit' => 255, 'null' => false ] )",
			$contents
		);

		if( $patched === $contents )
		{
			return false;
		}

		if( file_put_contents( $path, $patched ) === false )
		{
			return false;
		}

		$this->output->info( 'Patched nullable primary key on ' . basename( $path ) );

		return true;
	}

	/**
	 * Run db:migrate through the CLI registry.
	 *
	 * @return bool
	 */
	protected function runMigration(): bool
	{
		try
		{
			$app = Registry::getInstance()->get( RegistryKeys::CLI_APPLICATION_LEGACY );

			if( !$app )
			{
				$this->output->error( 'CLI application not found in registry.' );
				$this->output->info( 'Run: ./vendor/bin/neuron db:migrate' );
				return false;
			}

			if( !method_exists( $app, 'has' ) || !$app->has( 'db:migrate' ) )
			{
				$this->output->error( 'db:migrate command not found.' );
				return false;
			}

			$commandClass = $app->getRegistry()->get( 'db:migrate' );

			if( !is_string( $commandClass ) || !class_exists( $commandClass ) )
			{
				$this->output->error( 'Migrate command class not found.' );
				return false;
			}

			$migrateCommand = new $commandClass();
			$migrateCommand->setInput( $this->input );
			$migrateCommand->setOutput( $this->output );
			$migrateCommand->configure();

			$exitCode = $migrateCommand->execute();

			if( $exitCode !== 0 )
			{
				$this->output->error( 'Migration failed.' );
				return false;
			}

			$this->output->success( 'Queue tables installed.' );
			return true;
		}
		catch( \Exception $exception )
		{
			$this->output->error( 'Error running migration: ' . $exception->getMessage() );
			return false;
		}
	}
}
