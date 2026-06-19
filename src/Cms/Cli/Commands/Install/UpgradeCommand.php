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
		$this->addOption( 'force-views', null, false, 'Overwrite existing view files with package versions (destroys local view customizations)' );
		$this->addOption( 'prompt-views', null, false, 'Prompt before overwriting each view that is newer in the package and differs from the local copy' );
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

		// --force-views / --prompt-views always have potential work to do: they
		// re-examine published views even when the manifest version is unchanged.
		$forceViews  = (bool) $this->input->getOption( 'force-views' );
		$promptViews = (bool) $this->input->getOption( 'prompt-views' );

		if( !$hasUpdates && !$forceViews && !$promptViews )
		{
			$this->output->success( "✓ CMS is already up to date!" );
			return 0;
		}

		if( $forceViews )
		{
			$this->output->writeln( "  ⚠️  --force-views: existing view files will be overwritten with package versions" );
		}
		elseif( $promptViews )
		{
			$this->output->writeln( "  ℹ️  --prompt-views: you will be asked before overwriting each changed view" );
		}

		// If --check flag, exit after displaying what would be updated
		if( $this->input->getOption( 'check' ) )
		{
			$this->output->info( "Run 'cms:upgrade' without --check to apply updates" );
			return 0;
		}

		// Confirm upgrade
		$this->output->writeln( "" );
		if( !$this->confirm( "Proceed with upgrade?", true ) )
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

			$this->output->writeln( "\n🕒 Scaffolding scheduled jobs configuration..." );
			$success = $success && $this->scaffoldScheduleConfig();
		}

		if( !$success )
		{
			$this->output->error( "Upgrade failed!" );
			return 1;
		}

		// Update installed manifest
		if( !$this->updateInstalledManifest() )
		{
			$this->output->warning( "Upgrade completed but manifest update failed. You may need to re-run cms:upgrade." );
		}

		// Display summary
		// Display summary
		$this->displaySummary();

		// Optionally run migrations
		if( $this->input->getOption( 'run-migrations' ) ||
		    $this->confirm( "\nRun database migrations now?", false ) )
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
			'/db/migrate'
		];

		foreach( $indicators as $path )
		{
			if( !file_exists( $this->_projectPath . $path ) )
			{
				return false;
			}
		}

		// Routing config: current installs use config/routing.yaml.
		// Older installs may still have config/routes.yaml, so accept either.
		if( !file_exists( $this->_projectPath . '/config/routing.yaml' )
			&& !file_exists( $this->_projectPath . '/config/routes.yaml' ) )
		{
			return false;
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

		$this->output->writeln( "Installed Version: $installedVersion" );
		$this->output->writeln( "Package Version:   $packageVersion\n" );
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
			$this->output->writeln( "New Migrations Available:" );

			foreach( $newMigrations as $migration )
			{
				$this->output->writeln( "  + $migration" );
			}
		}

		// Check for new view files (respecting view-related flags)
		if( !$this->input->getOption( 'migrations-only' ) && !$this->input->getOption( 'skip-views' ) )
		{
			$newViews = $this->getNewViewFiles();

			if( !empty( $newViews ) )
			{
				$hasUpdates = true;
				$this->output->writeln( "New Views Available:" );

				foreach( $newViews as $view )
				{
					$this->output->writeln( "  + resources/views/$view" );
				}
			}

			// A missing scheduled jobs config can be scaffolded
			if( !file_exists( $this->_projectPath . '/config/schedule.yaml' )
				&& file_exists( $this->_componentPath . '/resources/config/schedule.yaml' ) )
			{
				$hasUpdates = true;
				$this->output->writeln( "Scheduled jobs config can be scaffolded:" );
				$this->output->writeln( "  + config/schedule.yaml" );
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
				$this->output->writeln( "Version update available (no database changes)" );
			}
		}

		return $hasUpdates;
	}

	/**
	 * Get list of package view files that are not yet present in the installation.
	 *
	 * @return array Relative view paths (e.g. "admin/jobs/index.php")
	 */
	private function getNewViewFiles(): array
	{
		$viewSource = $this->_componentPath . '/resources/views';
		$viewDest   = $this->_projectPath . '/resources/views';

		if( !is_dir( $viewSource ) )
		{
			return [];
		}

		return $this->findMissingViews( $viewSource, $viewDest, '' );
	}

	/**
	 * Recursively collect view files present in the package but missing from
	 * the installation.
	 *
	 * @param string $source Package view directory
	 * @param string $dest Installation view directory
	 * @param string $relative Relative path accumulated so far
	 * @return array Relative view paths
	 */
	private function findMissingViews( string $source, string $dest, string $relative ): array
	{
		$items = scandir( $source );

		if( $items === false )
		{
			return [];
		}

		$missing = [];

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$sourcePath   = $source . '/' . $item;
			$destPath     = $dest . '/' . $item;
			$itemRelative = $relative === '' ? $item : $relative . '/' . $item;

			if( is_dir( $sourcePath ) )
			{
				$missing = array_merge( $missing, $this->findMissingViews( $sourcePath, $destPath, $itemRelative ) );
				continue;
			}

			if( !file_exists( $destPath ) )
			{
				$missing[] = $itemRelative;
			}
		}

		return $missing;
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
			$this->output->writeln( "\n  Copied $copied new migration" . ( $copied > 1 ? 's' : '' ) );
		}

		return true;
	}

	/**
	 * Update view files.
	 *
	 * Copies only views that do not already exist in the installation. Existing
	 * view files are never overwritten so user customizations are preserved.
	 */
	private function updateViews(): bool
	{
		$viewSource = $this->_componentPath . '/resources/views';
		$viewDest   = $this->_projectPath . '/resources/views';

		if( !is_dir( $viewSource ) )
		{
			$this->output->writeln( "  No package views found to copy" );
			return true;
		}

		$force  = (bool) $this->input->getOption( 'force-views' );
		$prompt = (bool) $this->input->getOption( 'prompt-views' );

		// Interactive merge: add new views, and ask per file for views that are
		// newer in the package and differ from the local copy.
		if( $prompt && !$force )
		{
			$copied = $this->copyViewsInteractive( $viewSource, $viewDest );

			if( $copied > 0 )
			{
				$this->output->writeln( "\n  Copied $copied view file" . ( $copied !== 1 ? 's' : '' ) );
			}
			else
			{
				$this->output->writeln( "  No view files copied" );
			}

			$this->output->writeln( "  ℹ️  Unchanged and declined views were left as-is" );
			$this->output->writeln( "  Package views location: " . $viewSource . "/" );

			return true;
		}

		$copied = $this->copyNewViews( $viewSource, $viewDest, $force );

		if( $copied > 0 )
		{
			$label = $force ? 'view file' : 'new view file';
			$this->output->writeln( "\n  Copied $copied $label" . ( $copied !== 1 ? 's' : '' ) );
		}
		else
		{
			$this->output->writeln( "  No " . ( $force ? '' : 'new ' ) . "view files to copy" );
		}

		if( !$force )
		{
			$this->output->writeln( "  ℹ️  Existing views were left unchanged to preserve customizations" );
			$this->output->writeln( "  Compare package views with your installation if needed" );
		}

		$this->output->writeln( "  Package views location: " . $viewSource . "/" );

		return true;
	}

	/**
	 * Recursively copy view files into the destination.
	 *
	 * By default, existing files are skipped (never overwritten) to preserve
	 * user customizations. When $force is true, existing files are overwritten
	 * with the package versions. Missing destination directories are created as
	 * needed.
	 *
	 * @param string $source Source directory
	 * @param string $dest Destination directory
	 * @param bool $force Overwrite existing files when true
	 * @return int Number of files copied
	 */
	private function copyNewViews( string $source, string $dest, bool $force = false ): int
	{
		$items = scandir( $source );

		if( $items === false )
		{
			return 0;
		}

		$copied = 0;

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$sourcePath = $source . '/' . $item;
			$destPath   = $dest . '/' . $item;

			if( is_dir( $sourcePath ) )
			{
				$copied += $this->copyNewViews( $sourcePath, $destPath, $force );
				continue;
			}

			$exists = file_exists( $destPath );

			// Preserve customizations unless force overwrite is requested.
			if( $exists && !$force )
			{
				continue;
			}

			$destDir = dirname( $destPath );

			if( !is_dir( $destDir ) && !mkdir( $destDir, 0755, true ) && !is_dir( $destDir ) )
			{
				$this->output->error( "  ✗ Failed to create directory: $destDir" );
				continue;
			}

			$relative = ltrim( str_replace( $this->_projectPath, '', $destPath ), '/' );

			if( copy( $sourcePath, $destPath ) )
			{
				$verb = $exists ? 'Updated' : 'Added';
				$this->output->writeln( "  ✓ $verb: $relative" );
				$this->_messages[] = "$verb view: $relative";
				$copied++;
			}
			else
			{
				$this->output->error( "  ✗ Failed to copy: $relative" );
			}
		}

		return $copied;
	}

	/**
	 * Recursively copy views, prompting before overwriting changed files.
	 *
	 * Missing views are added automatically. An existing view is only offered
	 * for overwrite when the package copy is newer (by modification time) AND
	 * its contents differ from the local copy, so identical or locally-newer
	 * files are skipped without noise.
	 *
	 * @param string $source Source directory
	 * @param string $dest Destination directory
	 * @return int Number of files copied
	 */
	private function copyViewsInteractive( string $source, string $dest ): int
	{
		$items = scandir( $source );

		if( $items === false )
		{
			return 0;
		}

		$copied = 0;

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$sourcePath = $source . '/' . $item;
			$destPath   = $dest . '/' . $item;

			if( is_dir( $sourcePath ) )
			{
				$copied += $this->copyViewsInteractive( $sourcePath, $destPath );
				continue;
			}

			$relative = ltrim( str_replace( $this->_projectPath, '', $destPath ), '/' );

			// New view: add without prompting.
			if( !file_exists( $destPath ) )
			{
				if( $this->copyViewFile( $sourcePath, $destPath ) )
				{
					$this->output->writeln( "  ✓ Added: $relative" );
					$this->_messages[] = "Added view: $relative";
					$copied++;
				}

				continue;
			}

			// Only consider views the package updated more recently.
			if( filemtime( $sourcePath ) <= filemtime( $destPath ) )
			{
				continue;
			}

			// Skip when contents are identical despite the newer timestamp.
			if( md5_file( $sourcePath ) === md5_file( $destPath ) )
			{
				continue;
			}

			$packageDate = date( 'Y-m-d H:i', (int) filemtime( $sourcePath ) );
			$localDate   = date( 'Y-m-d H:i', (int) filemtime( $destPath ) );

			$this->output->writeln( "  • $relative (package $packageDate is newer than local $localDate)" );

			if( !$this->confirm( "    Overwrite this view?", false ) )
			{
				$this->output->writeln( "  – Skipped: $relative" );
				continue;
			}

			if( $this->copyViewFile( $sourcePath, $destPath ) )
			{
				$this->output->writeln( "  ✓ Updated: $relative" );
				$this->_messages[] = "Updated view: $relative";
				$copied++;
			}
		}

		return $copied;
	}

	/**
	 * Copy a single view file, creating the destination directory as needed.
	 *
	 * @param string $source Source file
	 * @param string $dest Destination file
	 * @return bool
	 */
	private function copyViewFile( string $source, string $dest ): bool
	{
		$destDir = dirname( $dest );

		if( !is_dir( $destDir ) && !mkdir( $destDir, 0755, true ) && !is_dir( $destDir ) )
		{
			$this->output->error( "  ✗ Failed to create directory: $destDir" );
			return false;
		}

		if( !copy( $source, $dest ) )
		{
			$this->output->error( "  ✗ Failed to copy: $dest" );
			return false;
		}

		return true;
	}

	/**
	 * Scaffold the scheduled jobs configuration.
	 *
	 * Copies the default config/schedule.yaml when the installation does not
	 * already have one. An existing schedule.yaml is never overwritten so user
	 * customizations are preserved.
	 *
	 * @return bool
	 */
	private function scaffoldScheduleConfig(): bool
	{
		$scheduleFile = $this->_projectPath . '/config/schedule.yaml';
		$resourceFile = $this->_componentPath . '/resources/config/schedule.yaml';

		if( file_exists( $scheduleFile ) )
		{
			$this->output->writeln( "  ℹ️  schedule.yaml already exists; left unchanged" );
			return true;
		}

		if( !file_exists( $resourceFile ) )
		{
			$this->output->writeln( "  No default schedule.yaml available to scaffold" );
			return true;
		}

		if( copy( $resourceFile, $scheduleFile ) )
		{
			$this->output->writeln( "  ✓ Created: config/schedule.yaml" );
			$this->_messages[] = "Created config/schedule.yaml";
			return true;
		}

		$this->output->error( "  ✗ Failed to create config/schedule.yaml" );
		return false;
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
