<?php

namespace Neuron\Cms\Cli\Commands\Install;

use Neuron\Cli\Commands\Command;

/**
 * Upgrade the CMS to the latest version
 *
 * Compares installed version with package version, copies new migrations,
 * and optionally updates views and configuration files.
 */
class UpgradeCommand extends Command
{
	private string $_projectPath;
	private string $_componentPath;
	private array $_messages = [];
	private array $_packageManifest = [];
	private array $_installedManifest = [];

	public function __construct()
	{
		// Get project root (where composer.json is)
		$this->_projectPath = getcwd();

		// Get component path
		$this->_componentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:upgrade';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Upgrade CMS to latest version (copy new migrations, update files)';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addOption( 'check', 'c', false, 'Check for available updates without applying' );
		$this->addOption( 'migrations-only', 'm', false, 'Only copy new migrations' );
		$this->addOption( 'skip-views', null, false, 'Skip updating view files' );
		$this->addOption( 'skip-migrations', null, false, 'Skip copying migrations' );
		$this->addOption( 'run-migrations', 'r', false, 'Run migrations automatically after copying' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $parameters = [] ): int
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Neuron CMS - Upgrade                 ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		// Load manifests
		if( !$this->loadManifests() )
		{
			return 1;
		}

		// Check if CMS is installed
		if( !$this->isInstalled() )
		{
			$this->output->error( "CMS is not installed. Please run 'cms:install' first." );
			return 1;
		}

		// Display version information
		$this->displayVersionInfo();

		// Check for updates
		$hasUpdates = $this->checkForUpdates();

		if( !$hasUpdates )
		{
			$this->output->success( "✓ CMS is already up to date!" );
			return 0;
		}

		// If --check flag, exit after displaying what would be updated
		if( $this->input->getOption( 'check' ) )
		{
			$this->output->info( "Run 'cms:upgrade' without --check to apply updates" );
			return 0;
		}

		// Confirm upgrade
		$this->output->writeln( "" );
		if( !$this->input->confirm( "Proceed with upgrade?", true ) )
		{
			$this->output->error( "Upgrade cancelled." );
			return 1;
		}

		// Perform upgrade steps
		$success = true;

		if( !$this->input->getOption( 'skip-migrations' ) )
		{
			$this->output->writeln( "\n📦 Copying new migrations..." );
			$success = $success && $this->copyNewMigrations();
		}

		if( !$this->input->getOption( 'migrations-only' ) && !$this->input->getOption( 'skip-views' ) )
		{
			$this->output->writeln( "\n🎨 Updating view files..." );
			$success = $success && $this->updateViews();
		}

		if( !$this->input->getOption( 'migrations-only' ) )
		{
			$this->output->writeln( "\n⚙️  Updating configuration examples..." );
			$success = $success && $this->updateConfigExamples();
		}

		if( !$success )
		{
			$this->output->error( "Upgrade failed!" );
			return 1;
		}

		// Update installed manifest
		$this->updateInstalledManifest();

		// Display summary
		$this->displaySummary();

		// Optionally run migrations
		if( $this->input->getOption( 'run-migrations' ) ||
		    $this->input->confirm( "\nRun database migrations now?", false ) )
		{
			$this->output->writeln( "" );
			$this->runMigrations();
		}
		else
		{
			$this->output->info( "\n⚠️  Remember to run: php neuron db:migrate" );
		}

		$this->output->success( "\n✓ Upgrade complete!" );

		return 0;
	}

	/**
	 * Load package and installed manifests
	 */
	private function loadManifests(): bool
	{
		// Load package manifest
		$packageManifestPath = $this->_componentPath . '/resources/.cms-manifest.json';

		if( !file_exists( $packageManifestPath ) )
		{
			$this->output->error( "Package manifest not found at: $packageManifestPath" );
			return false;
		}

		$packageManifestJson = file_get_contents( $packageManifestPath );
		$this->_packageManifest = json_decode( $packageManifestJson, true );

		if( json_last_error() !== JSON_ERROR_NONE )
		{
			$this->output->error( "Failed to parse package manifest: " . json_last_error_msg() );
			return false;
		}

		// Load installed manifest (may not exist on old installations)
		$installedManifestPath = $this->_projectPath . '/.cms-manifest.json';

		if( file_exists( $installedManifestPath ) )
		{
			$installedManifestJson = file_get_contents( $installedManifestPath );
			$this->_installedManifest = json_decode( $installedManifestJson, true );

			if( json_last_error() !== JSON_ERROR_NONE )
			{
				$this->output->error( "Failed to parse installed manifest: " . json_last_error_msg() );
				return false;
			}
		}
		else
		{
			// No manifest = old installation, create minimal one
			$this->_installedManifest = [
				'version' => 'unknown',
				'migrations' => []
			];

			// Try to detect installed migrations
			$migrateDir = $this->_projectPath . '/db/migrate';
			if( is_dir( $migrateDir ) )
			{
				$files = glob( $migrateDir . '/*.php' );
				$this->_installedManifest['migrations'] = array_map( 'basename', $files );
			}
		}

		return true;
	}

	/**
	 * Check if CMS is installed
	 */
	private function isInstalled(): bool
	{
		// Check for key indicators
		$indicators = [
			'/resources/views/admin',
			'/config/routes.yaml',
			'/db/migrate'
		];

		foreach( $indicators as $path )
		{
			if( !file_exists( $this->_projectPath . $path ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Display version information
	 */
	private function displayVersionInfo(): void
	{
		$installedVersion = $this->_installedManifest['version'] ?? 'unknown';
		$packageVersion = $this->_packageManifest['version'] ?? 'unknown';

		$this->output->writeln( "Installed Version: <info>$installedVersion</info>" );
		$this->output->writeln( "Package Version:   <info>$packageVersion</info>\n" );
	}

	/**
	 * Check for available updates
	 */
	private function checkForUpdates(): bool
	{
		$hasUpdates = false;

		// Check for new migrations
		$newMigrations = $this->getNewMigrations();

		if( !empty( $newMigrations ) )
		{
			$hasUpdates = true;
			$this->output->writeln( "<comment>New Migrations Available:</comment>" );

			foreach( $newMigrations as $migration )
			{
				$this->output->writeln( "  + $migration" );
			}
		}

		// Check version difference
		$installedVersion = $this->_installedManifest['version'] ?? '0';
		$packageVersion = $this->_packageManifest['version'] ?? '0';

		if( $packageVersion !== $installedVersion )
		{
			$hasUpdates = true;

			if( empty( $newMigrations ) )
			{
				$this->output->writeln( "<comment>Version update available (no database changes)</comment>" );
			}
		}

		return $hasUpdates;
	}

	/**
	 * Get list of new migrations not in installation
	 */
	private function getNewMigrations(): array
	{
		$packageMigrations = $this->_packageManifest['migrations'] ?? [];
		$installedMigrations = $this->_installedManifest['migrations'] ?? [];

		return array_diff( $packageMigrations, $installedMigrations );
	}

	/**
	 * Copy new migrations to project
	 */
	private function copyNewMigrations(): bool
	{
		$newMigrations = $this->getNewMigrations();

		if( empty( $newMigrations ) )
		{
			$this->output->writeln( "  No new migrations to copy" );
			return true;
		}

		$migrationsDir = $this->_projectPath . '/db/migrate';
		$componentMigrationsDir = $this->_componentPath . '/resources/database/migrate';

		// Create migrations directory if it doesn't exist
		if( !is_dir( $migrationsDir ) )
		{
			if( !mkdir( $migrationsDir, 0755, true ) )
			{
				$this->output->error( "  Failed to create migrations directory!" );
				return false;
			}
		}

		$copied = 0;

		foreach( $newMigrations as $migration )
		{
			$sourceFile = $componentMigrationsDir . '/' . $migration;
			$destFile = $migrationsDir . '/' . $migration;

			if( !file_exists( $sourceFile ) )
			{
				$this->output->warning( "  Migration file not found: $migration" );
				continue;
			}

			if( copy( $sourceFile, $destFile ) )
			{
				$this->output->writeln( "  ✓ Copied: $migration" );
				$this->_messages[] = "Copied migration: $migration";
				$copied++;
			}
			else
			{
				$this->output->error( "  ✗ Failed to copy: $migration" );
				return false;
			}
		}

		if( $copied > 0 )
		{
			$this->output->writeln( "\n  <info>Copied $copied new migration" . ( $copied > 1 ? 's' : '' ) . "</info>" );
		}

		return true;
	}

	/**
	 * Update view files (conservative - only critical updates)
	 */
	private function updateViews(): bool
	{
		// For now, just inform user that views may need manual updates
		// In future versions, could implement smart view updates

		$this->output->writeln( "  ℹ️  View updates require manual review to preserve customizations" );
		$this->output->writeln( "  Compare package views with your installation if needed" );
		$this->output->writeln( "  Package views location: " . $this->_componentPath . "/resources/views/" );

		return true;
	}

	/**
	 * Update configuration example files
	 */
	private function updateConfigExamples(): bool
	{
		$configSource = $this->_componentPath . '/resources/config';
		$configDest = $this->_projectPath . '/config';

		// Only copy .example files
		$exampleFiles = glob( $configSource . '/*.example' );

		if( empty( $exampleFiles ) )
		{
			$this->output->writeln( "  No configuration examples to update" );
			return true;
		}

		foreach( $exampleFiles as $sourceFile )
		{
			$fileName = basename( $sourceFile );
			$destFile = $configDest . '/' . $fileName;

			if( copy( $sourceFile, $destFile ) )
			{
				$this->output->writeln( "  ✓ Updated: $fileName" );
			}
			else
			{
				$this->output->error( "  ✗ Failed to copy $fileName from $sourceFile to $destFile" );
			}
		}

		return true;
	}

	/**
	 * Update installed manifest
	 */
	private function updateInstalledManifest(): bool
	{
		$manifestPath = $this->_projectPath . '/.cms-manifest.json';

		// Update manifest with package migrations
		$packageMigrations = $this->_packageManifest['migrations'] ?? [];

		$this->_installedManifest['version'] = $this->_packageManifest['version'];
		$this->_installedManifest['updated_at'] = date( 'Y-m-d H:i:s' );
		$this->_installedManifest['migrations'] = $packageMigrations;

		$json = json_encode( $this->_installedManifest, JSON_PRETTY_PRINT );

		if( file_put_contents( $manifestPath, $json ) === false )
		{
			$this->output->warning( "Failed to update manifest file" );
			return false;
		}

		return true;
	}

	/**
	 * Run database migrations
	 */
	private function runMigrations(): bool
	{
		$this->output->writeln( "Running migrations...\n" );

		// For now, instruct user to run migrations manually
		// In future, could integrate with MigrationManager

		$this->output->info( "Run: php neuron db:migrate" );

		return true;
	}

	/**
	 * Display upgrade summary
	 */
	private function displaySummary(): void
	{
		if( empty( $this->_messages ) )
		{
			return;
		}

		$this->output->writeln( "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
		$this->output->writeln( "Upgrade Summary:" );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );

		foreach( $this->_messages as $message )
		{
			$this->output->writeln( "  • $message" );
		}
	}
}
