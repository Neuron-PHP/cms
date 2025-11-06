<?php

namespace Neuron\Cms\Cli\Commands\Install;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

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
		$this->_ComponentPath = dirname( dirname( dirname( dirname( __DIR__ ) ) ) );
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

			$response = $this->prompt( "Do you want to reinstall? This will overwrite existing files. (yes/no): " );

			if( strtolower( trim( $response ) ) !== 'yes' )
			{
				$this->output( "\n❌ Installation cancelled.\n" );
				return self::FAILURE;
			}
		}

		// Run installation steps
		$steps = [
			'createDirectories' => 'Creating directories...',
			'publishViews' => 'Publishing admin view templates...',
			'createRouteConfig' => 'Creating route configuration...',
			'createAuthConfig' => 'Creating auth configuration...',
			'setupDatabase' => 'Setting up database...',
		];

		foreach( $steps as $method => $message )
		{
			$this->output( $message );

			if( !$this->$method() )
			{
				$this->output( "\n❌ Installation failed!\n" );
				return self::FAILURE;
			}
		}

		// Generate migration
		if( !$this->generateMigration() )
		{
			$this->output( "\n❌ Installation failed at migration generation!\n" );
			return self::FAILURE;
		}

		// Ask to run migration
		$this->output( "\n" );
		$response = $this->prompt( "Would you like to run the migration now? (yes/no): " );

		if( strtolower( trim( $response ) ) === 'yes' )
		{
			if( !$this->runMigration() )
			{
				$this->output( "\n❌ Migration failed!\n" );
				$this->output( "ℹ️  You can run it manually with: php neuron cms:migrate\n" );
				return self::FAILURE;
			}
		}
		else
		{
			$this->output( "\nℹ️  Remember to run migration with: php neuron cms:migrate\n" );
		}

		// Display summary
		$this->output( "\n✅ Installation complete!\n" );
		$this->displaySummary();

		// Create first admin user
		$this->output( "\n" );
		$response = $this->prompt( "Would you like to create an admin user now? (yes/no): " );

		if( strtolower( trim( $response ) ) === 'yes' )
		{
			$this->createAdminUser();
		}
		else
		{
			$this->output( "\nℹ️  You can create an admin user later with: php neuron cms:user:create\n" );
		}

		return self::SUCCESS;
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
			'/resources/views/admin',
			'/resources/views/admin/layouts',
			'/resources/views/admin/auth',
			'/resources/views/admin/dashboard',
			'/resources/views/admin/users',
			'/storage',
			'/storage/migrations',
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
		$viewSource = $this->_ComponentPath . '/resources/views/admin';
		$viewDest = $this->_ProjectPath . '/resources/views/admin';

		if( !is_dir( $viewSource ) )
		{
			$this->output( "  ⚠️  Warning: Source views not found at: $viewSource" );
			return true; // Don't fail, views might not exist yet
		}

		// Copy all view files recursively
		return $this->copyDirectory( $viewSource, $viewDest );
	}

	/**
	 * Create route configuration example
	 */
	private function createRouteConfig(): bool
	{
		$routeFile = $this->_ProjectPath . '/config/routes.yaml';
		$exampleFile = $this->_ComponentPath . '/examples/config/routes.yaml';

		// If routes.yaml doesn't exist, copy example
		if( !file_exists( $routeFile ) && file_exists( $exampleFile ) )
		{
			if( copy( $exampleFile, $routeFile ) )
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
				$this->_Messages[] = "   See examples/config/routes.yaml for reference";
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
		$exampleFile = $this->_ComponentPath . '/config/auth.yaml';

		// If auth.yaml doesn't exist, copy example
		if( !file_exists( $authFile ) && file_exists( $exampleFile ) )
		{
			if( copy( $exampleFile, $authFile ) )
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

		$this->output( "Select database adapter:" );
		$this->output( "  1) SQLite (recommended - simple, no server required)" );
		$this->output( "  2) MySQL" );
		$this->output( "  3) PostgreSQL" );
		$this->output( "" );

		$choice = $this->prompt( "Enter choice (1-3): " );

		$config = [];

		switch( trim( $choice ) )
		{
			case '1':
				$config = $this->configureSqlite();
				break;

			case '2':
				$config = $this->configureMysql();
				break;

			case '3':
				$config = $this->configurePostgresql();
				break;

			default:
				$this->output( "\n❌ Invalid choice. Using SQLite as default.\n" );
				$config = $this->configureSqlite();
		}

		if( !$config )
		{
			return false;
		}

		// Save configuration
		return $this->saveConfig( $config );
	}

	/**
	 * Configure SQLite
	 */
	private function configureSqlite(): array
	{
		$this->output( "\n--- SQLite Configuration ---\n" );

		$dbPath = $this->prompt( "Database file path (storage/database.sqlite): " );
		$dbPath = trim( $dbPath ) ?: 'storage/database.sqlite';

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

		$host = $this->prompt( "Host (localhost): " );
		$host = trim( $host ) ?: 'localhost';

		$port = $this->prompt( "Port (3306): " );
		$port = trim( $port ) ?: '3306';

		$name = $this->prompt( "Database name: " );
		$name = trim( $name );

		if( !$name )
		{
			$this->output( "❌ Database name is required!" );
			return [];
		}

		$user = $this->prompt( "Username: " );
		$user = trim( $user );

		if( !$user )
		{
			$this->output( "❌ Username is required!" );
			return [];
		}

		$pass = $this->prompt( "Password: " );

		$charset = $this->prompt( "Charset (utf8mb4): " );
		$charset = trim( $charset ) ?: 'utf8mb4';

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

		$host = $this->prompt( "Host (localhost): " );
		$host = trim( $host ) ?: 'localhost';

		$port = $this->prompt( "Port (5432): " );
		$port = trim( $port ) ?: '5432';

		$name = $this->prompt( "Database name: " );
		$name = trim( $name );

		if( !$name )
		{
			$this->output( "❌ Database name is required!" );
			return [];
		}

		$user = $this->prompt( "Username: " );
		$user = trim( $user );

		if( !$user )
		{
			$this->output( "❌ Username is required!" );
			return [];
		}

		$pass = $this->prompt( "Password: " );

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
		$timestamp = date( 'YmdHis' );
		$className = $migrationName;
		$fileName = $timestamp . '_' . $this->camelToSnake( $migrationName ) . '.php';
		$filePath = $this->_ProjectPath . '/storage/migrations/' . $fileName;

		$template = $this->getMigrationTemplate( $className );

		if( file_put_contents( $filePath, $template ) === false )
		{
			$this->output( "❌ Failed to create migration file!" );
			return false;
		}

		$this->_Messages[] = "Created: storage/migrations/$fileName";
		return true;
	}

	/**
	 * Get migration template with users table schema
	 */
	private function getMigrationTemplate( string $ClassName ): string
	{
		return <<<PHP
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create users table
 */
class $ClassName extends AbstractMigration
{
	/**
	 * Create users table
	 */
	public function change()
	{
		\$table = \$this->table( 'users' );

		\$table->addColumn( 'username', 'string', [ 'limit' => 255 ] )
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

		// Load database config
		$configFile = $this->_ProjectPath . '/config/config.yaml';
		if( !file_exists( $configFile ) )
		{
			$this->output( "❌ Configuration file not found!" );
			return false;
		}

		try
		{
			$yaml = new Yaml( $configFile );
			$settings = new SettingManager( $yaml );
			$dbConfig = $this->getDatabaseConfig( $settings );

			if( !$dbConfig )
			{
				$this->output( "❌ Database configuration not found!" );
				return false;
			}
		}
		catch( \Exception $e )
		{
			$this->output( "❌ Failed to load configuration: " . $e->getMessage() );
			return false;
		}

		// Execute migration command
		$output = [];
		$returnCode = 0;

		exec( 'cd ' . escapeshellarg( $this->_ProjectPath ) . ' && php neuron cms:migrate 2>&1', $output, $returnCode );

		foreach( $output as $line )
		{
			$this->output( $line );
		}

		if( $returnCode !== 0 )
		{
			$this->output( "\n❌ Migration failed with exit code: $returnCode" );
			return false;
		}

		$this->output( "\n✅ Migration completed successfully!" );
		return true;
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
		$username = $this->prompt( "Enter username (admin): " );
		$username = trim( $username ) ?: 'admin';

		// Check if user exists
		if( $repository->findByUsername( $username ) )
		{
			$this->output( "\n❌ Error: User '$username' already exists!\n" );
			return false;
		}

		// Get email
		$email = $this->prompt( "Enter email (admin@example.com): " );
		$email = trim( $email ) ?: 'admin@example.com';

		// Get password
		$password = $this->prompt( "Enter password (min 8 characters): " );

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
	 * Prompt for user input
	 */
	private function prompt( string $Message ): string
	{
		echo $Message;
		return trim( fgets( STDIN ) );
	}

	/**
	 * Output message
	 */
	private function output( string $Message ): void
	{
		echo $Message . "\n";
	}
}
