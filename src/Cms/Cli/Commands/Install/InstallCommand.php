<?php

namespace Neuron\Cms\Cli\Commands\Install;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;
use Neuron\Patterns\Registry;

/**
 * Install the CMS admin UI into the project
 *
 * Publishes view templates, creates directories, and sets up initial admin user
 */
class InstallCommand extends Command
{
	private string $_ProjectPath;
	private string $_ComponentPath;
	private array $_Messages = [];

	public function __construct()
	{
		// Get project root (where composer.json is)
		$this->_ProjectPath = getcwd();

		// Get component path
		$this->_ComponentPath = dirname( dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:install';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Install CMS admin UI into your project';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		// Additional configuration can go here
	}

	/**
	 * Execute the command
	 */
	public function execute( array $Parameters = [] ): int
	{
		$this->output( "\n╔═══════════════════════════════════════╗" );
		$this->output( "║  Neuron CMS - Installation           ║" );
		$this->output( "╚═══════════════════════════════════════╝\n" );

		// Check if already installed
		if( $this->isAlreadyInstalled() )
		{
			$this->output( "⚠️  Admin UI appears to be already installed." );
			$this->output( "Resources directory exists: resources/views/admin/" );
			$this->output( "" );

			if( !$this->input->confirm( "Do you want to reinstall? This will overwrite existing files", false ) )
			{
				$this->output( "\n❌ Installation cancelled.\n" );
				return 1;
			}
		}

		// Run installation steps
		$steps = [
			'createDirectories' => 'Creating directories...',
			'publishViews' => 'Publishing view templates...',
			'publishInitializers' => 'Publishing initializers...',
			'createRouteConfig' => 'Creating route configuration...',
			'createAuthConfig' => 'Creating auth configuration...',
			'createPublicFiles' => 'Creating public folder and copying static assets...',
			'setupDatabase' => 'Setting up database...',
		];

		foreach( $steps as $method => $message )
		{
			$this->output( $message );

			if( !$this->$method() )
			{
				$this->output( "\n❌ Installation failed!\n" );
				return 1;
			}
		}

		// Generate migration
		if( !$this->generateMigration() )
		{
			$this->output( "\n❌ Installation failed at migration generation!\n" );
			return 1;
		}

		// Ask to run migration
		$this->output( "\n" );
		if( $this->input->confirm( "Would you like to run the migration now?", true ) )
		{
			if( !$this->runMigration() )
			{
				$this->output( "\n❌ Migration failed!\n" );
				$this->output( "ℹ️  You can run it manually with: php neuron db:migrate\n" );
				return 1;
			}
		}
		else
		{
			$this->output( "\nℹ️  Remember to run migration with: php neuron db:migrate\n" );
		}

		// Display summary
		$this->output( "\n✅ Installation complete!\n" );
		$this->displaySummary();

		// Create first admin user
		$this->output( "\n" );
		if( $this->input->confirm( "Would you like to create an admin user now?", true ) )
		{
			$this->createAdminUser();
		}
		else
		{
			$this->output( "\nℹ️  You can create an admin user later with: php neuron cms:user:create\n" );
		}

		return 0;
	}

	/**
	 * Check if CMS is already installed
	 */
	private function isAlreadyInstalled(): bool
	{
		return is_dir( $this->_ProjectPath . '/resources/views/admin' );
	}

	/**
	 * Create necessary directories
	 */
	private function createDirectories(): bool
	{
		$directories = [
			// View directories
			'/resources/views/admin',
			'/resources/views/admin/auth',
			'/resources/views/admin/dashboard',
			'/resources/views/admin/users',
			'/resources/views/admin/posts',
			'/resources/views/admin/categories',
			'/resources/views/admin/tags',
			'/resources/views/admin/profile',
			'/resources/views/auth',
			'/resources/views/blog',
			'/resources/views/content',
			'/resources/views/emails',
			'/resources/views/http_codes',
			'/resources/views/layouts',

			// Application directories
			'/app/Controllers',
			'/app/Models',
			'/app/Repositories',
			'/app/Services',
			'/app/Events',
			'/app/Listeners',
			'/app/Jobs',
			'/app/Initializers',

			// Storage directories
			'/storage',
			'/storage/logs',
			'/storage/cache',

			// Database directories
			'/db',
			'/db/migrate',
			'/db/seed',

			// Configuration directory
			'/config',
		];

		foreach( $directories as $dir )
		{
			$path = $this->_ProjectPath . $dir;

			if( !is_dir( $path ) )
			{
				if( !mkdir( $path, 0755, true ) )
				{
					$this->output( "  ❌ Failed to create: $dir" );
					return false;
				}

				$this->_Messages[] = "Created: $dir";
			}
		}

		return true;
	}

	/**
	 * Publish view templates
	 */
	private function publishViews(): bool
	{
		// Copy all view directories
		$viewDirs = [ 'admin', 'auth', 'blog', 'content', 'http_codes', 'layouts' ];

		foreach( $viewDirs as $dir )
		{
			$viewSource = $this->_ComponentPath . '/resources/views/' . $dir;
			$viewDest = $this->_ProjectPath . '/resources/views/' . $dir;

			if( !is_dir( $viewSource ) )
			{
				$this->output( "  ⚠️  Warning: Source views not found at: $viewSource" );
				continue; // Skip if directory doesn't exist
			}

			// Copy all view files recursively
			if( !$this->copyDirectory( $viewSource, $viewDest ) )
			{
				$this->output( "  ❌ Failed to copy views from: $dir" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Publish initializers
	 */
	private function publishInitializers(): bool
	{
		$initializerSource = $this->_ComponentPath . '/resources/app/Initializers';
		$initializerDest = $this->_ProjectPath . '/app/Initializers';

		if( !is_dir( $initializerSource ) )
		{
			$this->output( "  ⚠️  Warning: Source initializers not found at: $initializerSource" );
			return true; // Don't fail, initializers might not exist yet
		}

		// Copy all initializer files
		return $this->copyDirectory( $initializerSource, $initializerDest );
	}

	/**
	 * Create route configuration
	 */
	private function createRouteConfig(): bool
	{
		$routeFile = $this->_ProjectPath . '/config/routes.yaml';
		$resourceFile = $this->_ComponentPath . '/resources/config/routes.yaml';

		// If routes.yaml doesn't exist, copy from resources
		if( !file_exists( $routeFile ) && file_exists( $resourceFile ) )
		{
			if( copy( $resourceFile, $routeFile ) )
			{
				$this->_Messages[] = "Created: config/routes.yaml";
				return true;
			}

			$this->output( "  ❌ Failed to create routes.yaml" );
			return false;
		}

		// If routes.yaml exists, check if it has admin routes
		if( file_exists( $routeFile ) )
		{
			$content = file_get_contents( $routeFile );

			if( strpos( $content, '/admin/dashboard' ) === false )
			{
				$this->_Messages[] = "⚠️  Please add admin routes to config/routes.yaml";
				$this->_Messages[] = "   See resources/config/routes.yaml for reference";
			}
		}

		return true;
	}

	/**
	 * Create auth configuration
	 */
	private function createAuthConfig(): bool
	{
		$authFile = $this->_ProjectPath . '/config/auth.yaml';
		$resourceFile = $this->_ComponentPath . '/resources/config/auth.yaml';

		// If auth.yaml doesn't exist, copy from resources
		if( !file_exists( $authFile ) && file_exists( $resourceFile ) )
		{
			if( copy( $resourceFile, $authFile ) )
			{
				$this->_Messages[] = "Created: config/auth.yaml";
				return true;
			}

			$this->output( "  ❌ Failed to create auth.yaml" );
			return false;
		}

		return true;
	}

	/**
	 * Create public folder and copy all static assets
	 */
	private function createPublicFiles(): bool
	{
		$publicDir = $this->_ProjectPath . '/public';

		// Create public directory if it doesn't exist
		if( !is_dir( $publicDir ) )
		{
			if( !mkdir( $publicDir, 0755, true ) )
			{
				$this->output( "  ❌ Failed to create public directory" );
				return false;
			}
		}

		// Copy all files from resources/public
		$sourceDir = $this->_ComponentPath . '/resources/public';

		if( !is_dir( $sourceDir ) )
		{
			$this->output( "  ❌ Source public directory not found" );
			return false;
		}

		$files = scandir( $sourceDir );

		foreach( $files as $file )
		{
			if( $file === '.' || $file === '..' )
			{
				continue;
			}

			$sourceFile = $sourceDir . '/' . $file;
			$destFile = $publicDir . '/' . $file;

			// Skip if destination file already exists
			if( file_exists( $destFile ) )
			{
				continue;
			}

			// Copy the file
			if( is_file( $sourceFile ) )
			{
				if( copy( $sourceFile, $destFile ) )
				{
					$this->_Messages[] = "Created: public/" . $file;
				}
				else
				{
					$this->output( "  ❌ Failed to copy: " . $file );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Copy directory recursively
	 */
	private function copyDirectory( string $Source, string $Dest ): bool
	{
		if( !is_dir( $Source ) )
		{
			return false;
		}

		if( !is_dir( $Dest ) )
		{
			mkdir( $Dest, 0755, true );
		}

		$items = scandir( $Source );

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$sourcePath = $Source . '/' . $item;
			$destPath = $Dest . '/' . $item;

			if( is_dir( $sourcePath ) )
			{
				if( !$this->copyDirectory( $sourcePath, $destPath ) )
				{
					return false;
				}
			}
			else
			{
				if( !copy( $sourcePath, $destPath ) )
				{
					$this->output( "  ❌ Failed to copy: $item" );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Setup database configuration
	 */
	private function setupDatabase(): bool
	{
		$this->output( "\n╔═══════════════════════════════════════╗" );
		$this->output( "║  Database Configuration               ║" );
		$this->output( "╚═══════════════════════════════════════╝\n" );

		$choice = $this->input->choice(
			"Select database adapter:",
			[
				'sqlite' => 'SQLite (recommended - simple, no server required)',
				'mysql' => 'MySQL',
				'pgsql' => 'PostgreSQL'
			],
			'sqlite'
		);

		$config = match( $choice )
		{
			'sqlite' => $this->configureSqlite(),
			'mysql' => $this->configureMysql(),
			'pgsql' => $this->configurePostgresql(),
			default => $this->configureSqlite()
		};

		if( !$config )
		{
			return false;
		}

		// Prompt for application settings
		$appConfig = $this->configureApplication();

		if( !$appConfig )
		{
			return false;
		}

		// Merge and save complete configuration
		return $this->saveCompleteConfig( $config, $appConfig );
	}

	/**
	 * Configure application settings
	 */
	private function configureApplication(): array
	{
		$this->output( "\n╔═══════════════════════════════════════╗" );
		$this->output( "║  Application Configuration            ║" );
		$this->output( "╚═══════════════════════════════════════╝\n" );

		// System timezone
		$defaultTimezone = date_default_timezone_get();
		$timezone = $this->input->ask( "System timezone", $defaultTimezone );

		// Site configuration
		$this->output( "\n--- Site Information ---\n" );

		$siteName = $this->input->ask( "Site name" );

		if( !$siteName )
		{
			$this->output( "❌ Site name is required!" );
			return [];
		}

		$siteTitle = $this->input->ask( "Site title (displayed in browser)", $siteName );
		$siteUrl = $this->input->ask( "Site URL (e.g., https://example.com)" );

		if( !$siteUrl )
		{
			$this->output( "❌ Site URL is required!" );
			return [];
		}

		$siteDescription = $this->input->ask( "Site description (optional)", "" );

		$this->_Messages[] = "Site: $siteName ($siteUrl)";
		$this->_Messages[] = "Timezone: $timezone";

		return [
			'timezone' => $timezone,
			'siteName' => $siteName,
			'siteTitle' => $siteTitle,
			'siteUrl' => $siteUrl,
			'siteDescription' => $siteDescription
		];
	}

	/**
	 * Save complete configuration with all required sections
	 */
	private function saveCompleteConfig( array $DatabaseConfig, array $AppConfig ): bool
	{
		// Build complete configuration
		$config = [
			'logging' => [
				'destination' => '\\Neuron\\Log\\Destination\\File',
				'format' => '\\Neuron\\Log\\Format\\PlainText',
				'file' => 'storage/app.log',
				'level' => 'debug'
			],
			'views' => [
				'path' => 'resources/views'
			],
			'system' => [
				'timezone' => $AppConfig['timezone'],
				'base_path' => $this->_ProjectPath
			],
			'site' => [
				'name' => $AppConfig['siteName'],
				'title' => $AppConfig['siteTitle'],
				'url' => $AppConfig['siteUrl'],
				'description' => $AppConfig['siteDescription']
			],
			'cache' => [
				'enabled' => false,
				'storage' => 'file',
				'path' => 'cache/views',
				'ttl' => 3600
			]
		];

		// Merge database configuration
		$config = array_merge( $config, $DatabaseConfig );

		// Save configuration
		return $this->saveConfig( $config );
	}

	/**
	 * Configure SQLite
	 */
	private function configureSqlite(): array
	{
		$this->output( "\n--- SQLite Configuration ---\n" );

		$dbPath = $this->input->ask( "Database file path", "storage/database.sqlite3" );

		// Make path absolute if relative
		if( !empty( $dbPath ) && $dbPath[0] !== '/' )
		{
			$dbPath = $this->_ProjectPath . '/' . $dbPath;
		}

		// Create directory if needed
		$dir = dirname( $dbPath );
		if( !is_dir( $dir ) )
		{
			if( !mkdir( $dir, 0755, true ) )
			{
				$this->output( "❌ Failed to create directory: $dir" );
				return [];
			}
		}

		$this->_Messages[] = "Database: SQLite ($dbPath)";

		return [
			'database' => [
				'adapter' => 'sqlite',
				'name' => $dbPath
			]
		];
	}

	/**
	 * Configure MySQL
	 */
	private function configureMysql(): array
	{
		$this->output( "\n--- MySQL Configuration ---\n" );

		$host = $this->input->ask( "Host", "localhost" );
		$port = $this->input->ask( "Port", "3306" );
		$name = $this->input->ask( "Database name" );

		if( !$name )
		{
			$this->output( "❌ Database name is required!" );
			return [];
		}

		$user = $this->input->ask( "Database username" );

		if( !$user )
		{
			$this->output( "❌ Username is required!" );
			return [];
		}

		$pass = $this->input->askSecret( "Database password" );
		$charset = $this->input->ask( "Character set (utf8mb4 recommended)", "utf8mb4" );

		$this->_Messages[] = "Database: MySQL ($host:$port/$name)";

		return [
			'database' => [
				'adapter' => 'mysql',
				'host' => $host,
				'port' => (int)$port,
				'name' => $name,
				'user' => $user,
				'pass' => $pass,
				'charset' => $charset
			]
		];
	}

	/**
	 * Configure PostgreSQL
	 */
	private function configurePostgresql(): array
	{
		$this->output( "\n--- PostgreSQL Configuration ---\n" );

		$host = $this->input->ask( "Host", "localhost" );
		$port = $this->input->ask( "Port", "5432" );
		$name = $this->input->ask( "Database name" );

		if( !$name )
		{
			$this->output( "❌ Database name is required!" );
			return [];
		}

		$user = $this->input->ask( "Database username" );

		if( !$user )
		{
			$this->output( "❌ Username is required!" );
			return [];
		}

		$pass = $this->input->askSecret( "Database password" );

		$this->_Messages[] = "Database: PostgreSQL ($host:$port/$name)";

		return [
			'database' => [
				'adapter' => 'pgsql',
				'host' => $host,
				'port' => (int)$port,
				'name' => $name,
				'user' => $user,
				'pass' => $pass
			]
		];
	}

	/**
	 * Save configuration to YAML file
	 */
	private function saveConfig( array $Config ): bool
	{
		$configFile = $this->_ProjectPath . '/config/config.yaml';

		// If config exists, merge with existing
		$existingConfig = [];
		if( file_exists( $configFile ) )
		{
			try
			{
				$yaml = new Yaml( $configFile );
				$settings = new SettingManager( $yaml );

				// Get all existing sections
				foreach( $settings->getSectionNames() as $section )
				{
					$existingConfig[$section] = [];
					foreach( $settings->getSectionSettingNames( $section ) as $name )
					{
						$value = $settings->get( $section, $name );
						if( $value !== null )
						{
							$existingConfig[$section][$name] = $value;
						}
					}
				}
			}
			catch( \Exception $e )
			{
				// Ignore, will create new
			}
		}

		// Merge configurations
		$finalConfig = array_merge( $existingConfig, $Config );

		// Convert to YAML manually (simple approach)
		$yamlContent = $this->arrayToYaml( $finalConfig );

		if( file_put_contents( $configFile, $yamlContent ) === false )
		{
			$this->output( "❌ Failed to save configuration file!" );
			return false;
		}

		$this->_Messages[] = "Created: config/config.yaml";
		return true;
	}

	/**
	 * Convert array to YAML format (simple implementation)
	 */
	private function arrayToYaml( array $Data, int $Indent = 0 ): string
	{
		$yaml = '';
		$indentStr = str_repeat( '  ', $Indent );

		foreach( $Data as $key => $value )
		{
			if( is_array( $value ) )
			{
				$yaml .= $indentStr . $key . ":\n";
				$yaml .= $this->arrayToYaml( $value, $Indent + 1 );
			}
			else
			{
				$yaml .= $indentStr . $key . ': ' . $this->yamlValue( $value ) . "\n";
			}
		}

		return $yaml;
	}

	/**
	 * Format value for YAML
	 */
	private function yamlValue( $Value ): string
	{
		if( is_bool( $Value ) )
		{
			return $Value ? 'true' : 'false';
		}

		if( is_int( $Value ) || is_float( $Value ) )
		{
			return (string)$Value;
		}

		if( is_string( $Value ) && ( strpos( $Value, ' ' ) !== false || strpos( $Value, ':' ) !== false ) )
		{
			return '"' . addslashes( $Value ) . '"';
		}

		return (string)$Value;
	}

	/**
	 * Generate database migration
	 */
	private function generateMigration(): bool
	{
		$this->output( "\nGenerating database migration..." );

		$migrationName = 'CreateUsersTable';
		$snakeCaseName = $this->camelToSnake( $migrationName );

		// Use db/migrate path to match MigrationManager expectations
		$migrationsDir = $this->_ProjectPath . '/db/migrate';

		// Create migrations directory if it doesn't exist
		if( !is_dir( $migrationsDir ) )
		{
			if( !mkdir( $migrationsDir, 0755, true ) )
			{
				$this->output( "❌ Failed to create migrations directory!" );
				return false;
			}
		}

		// Check if migration already exists
		$existingFiles = glob( $migrationsDir . '/*_' . $snakeCaseName . '.php' );
		if( !empty( $existingFiles ) )
		{
			$existingFile = basename( $existingFiles[0] );
			$this->output( "ℹ️  Migration already exists: $existingFile" );
			$this->_Messages[] = "Using existing migration: db/migrate/$existingFile";
			return true;
		}

		$timestamp = date( 'YmdHis' );
		$className = $migrationName;
		$fileName = $timestamp . '_' . $snakeCaseName . '.php';
		$filePath = $migrationsDir . '/' . $fileName;

		$template = $this->getMigrationTemplate( $className );

		if( file_put_contents( $filePath, $template ) === false )
		{
			$this->output( "❌ Failed to create migration file!" );
			return false;
		}

		$this->_Messages[] = "Created: db/migrate/$fileName";
		return true;
	}

	/**
	 * Get migration template with users and password_reset_tokens table schema
	 */
	private function getMigrationTemplate( string $ClassName ): string
	{
		return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create users and password_reset_tokens tables
 */
class $ClassName extends AbstractMigration
{
	/**
	 * Create users and password_reset_tokens tables
	 */
	public function change()
	{
		// Create users table
		\$usersTable = \$this->table( 'users' );

		\$usersTable->addColumn( 'username', 'string', [ 'limit' => 255 ] )
			->addColumn( 'email', 'string', [ 'limit' => 255 ] )
			->addColumn( 'password_hash', 'string', [ 'limit' => 255 ] )
			->addColumn( 'role', 'string', [ 'limit' => 50, 'default' => 'subscriber' ] )
			->addColumn( 'status', 'string', [ 'limit' => 50, 'default' => 'active' ] )
			->addColumn( 'email_verified', 'boolean', [ 'default' => false ] )
			->addColumn( 'two_factor_secret', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'remember_token', 'string', [ 'limit' => 255, 'null' => true ] )
			->addColumn( 'failed_login_attempts', 'integer', [ 'default' => 0 ] )
			->addColumn( 'locked_until', 'timestamp', [ 'null' => true ] )
			->addColumn( 'last_login_at', 'timestamp', [ 'null' => true ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'updated_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP' ] )
			->addIndex( [ 'username' ], [ 'unique' => true ] )
			->addIndex( [ 'email' ], [ 'unique' => true ] )
			->addIndex( [ 'remember_token' ] )
			->addIndex( [ 'status' ] )
			->create();

		// Create password_reset_tokens table
		\$tokensTable = \$this->table( 'password_reset_tokens' );

		\$tokensTable->addColumn( 'email', 'string', [ 'limit' => 255 ] )
			->addColumn( 'token', 'string', [ 'limit' => 64 ] )
			->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
			->addColumn( 'expires_at', 'timestamp', [ 'null' => false ] )
			->addIndex( [ 'email' ] )
			->addIndex( [ 'token' ] )
			->addIndex( [ 'expires_at' ] )
			->create();
	}
}

PHP;
	}

	/**
	 * Convert CamelCase to snake_case
	 */
	private function camelToSnake( string $Input ): string
	{
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $Input ) );
	}

	/**
	 * Run the migration
	 */
	private function runMigration(): bool
	{
		$this->output( "\nRunning migration...\n" );

		try
		{
			// Get the CLI application from the registry
			$app = Registry::getInstance()->get( 'cli.application' );

			if( !$app )
			{
				$this->output( "❌ CLI application not found in registry!" );
				return false;
			}

			// Check if db:migrate command exists
			if( !$app->has( 'db:migrate' ) )
			{
				$this->output( "❌ db:migrate command not found!" );
				return false;
			}

			// Get the migrate command class
			$commandClass = $app->getRegistry()->get( 'db:migrate' );

			if( !class_exists( $commandClass ) )
			{
				$this->output( "❌ Migrate command class not found: {$commandClass}" );
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
				$this->output( "\n❌ Migration failed with exit code: $exitCode" );
				return false;
			}

			$this->output( "\n✅ Migration completed successfully!" );
			return true;
		}
		catch( \Exception $e )
		{
			$this->output( "❌ Error running migration: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Display installation summary
	 */
	private function displaySummary(): void
	{
		$this->output( "Installation Summary:" );
		$this->output( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );

		foreach( $this->_Messages as $message )
		{
			$this->output( "  $message" );
		}

		$this->output( "\nNext Steps:" );
		$this->output( "  1. Create an admin user (prompted below)" );
		$this->output( "  2. Review config/routes.yaml for admin routes" );
		$this->output( "  3. Start your server and visit /login" );
		$this->output( "  4. Access admin at /admin/dashboard" );
	}

	/**
	 * Create admin user interactively
	 */
	private function createAdminUser(): bool
	{
		$this->output( "\n╔═══════════════════════════════════════╗" );
		$this->output( "║  Create Admin User                    ║" );
		$this->output( "╚═══════════════════════════════════════╝\n" );

		// Load database config
		$configFile = $this->_ProjectPath . '/config/config.yaml';
		if( !file_exists( $configFile ) )
		{
			$this->output( "\n❌ Configuration file not found!\n" );
			return false;
		}

		try
		{
			$yaml = new Yaml( $configFile );
			$settings = new SettingManager( $yaml );
			$dbConfig = $this->getDatabaseConfig( $settings );

			if( !$dbConfig )
			{
				$this->output( "\n❌ Database configuration not found!\n" );
				return false;
			}

			$repository = new DatabaseUserRepository( $dbConfig );
		}
		catch( \Exception $e )
		{
			$this->output( "\n❌ Database connection failed: " . $e->getMessage() . "\n" );
			return false;
		}

		$hasher = new PasswordHasher();

		// Get username
		$username = $this->input->ask( "Username (alphanumeric, 3-50 chars)", "admin" );

		// Check if user exists
		if( $repository->findByUsername( $username ) )
		{
			$this->output( "\n⚠️  Warning: User '$username' already exists!" );
			$this->output( "You can manage users with: php neuron cms:user:list\n" );
			return true;
		}

		// Get email
		$email = $this->input->ask( "Email address", "admin@example.com" );

		// Get password
		$this->output( "Password requirements: min 8 chars, uppercase, lowercase, number, special char" );
		$password = $this->input->askSecret( "Password" );

		if( strlen( $password ) < 8 )
		{
			$this->output( "\n❌ Error: Password must be at least 8 characters long!\n" );
			return false;
		}

		// Validate password
		if( !$hasher->meetsRequirements( $password ) )
		{
			$this->output( "\n❌ Error: Password does not meet requirements:" );

			foreach( $hasher->getValidationErrors( $password ) as $error )
			{
				$this->output( "  - $error" );
			}

			$this->output( "" );
			return false;
		}

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $hasher->hash( $password ) );
		$user->setRole( User::ROLE_ADMIN );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );

		try
		{
			$repository->create( $user );

			$this->output( "\n✅ Success! Admin user created:" );
			$this->output( "  Username: " . $user->getUsername() );
			$this->output( "  Email: " . $user->getEmail() );
			$this->output( "  Role: " . $user->getRole() );
			$this->output( "\n🚀 You can now login at: http://localhost:8000/login\n" );

			return true;
		}
		catch( \Exception $e )
		{
			$this->output( "\n❌ Error creating user: " . $e->getMessage() . "\n" );
			return false;
		}
	}

	/**
	 * Get database configuration from settings
	 */
	private function getDatabaseConfig( SettingManager $Settings ): ?array
	{
		try
		{
			$settingNames = $Settings->getSectionSettingNames( 'database' );

			if( empty( $settingNames ) )
			{
				return null;
			}

			$config = [];
			foreach( $settingNames as $name )
			{
				$value = $Settings->get( 'database', $name );
				if( $value !== null )
				{
					// Convert string values to appropriate types
					if( $name === 'port' )
					{
						$config[$name] = (int)$value;
					}
					else
					{
						$config[$name] = $value;
					}
				}
			}

			return $config;
		}
		catch( \Exception $e )
		{
			return null;
		}
	}

	/**
	 * Output message
	 */
	private function output( string $Message ): void
	{
		echo $Message . "\n";
	}
}
