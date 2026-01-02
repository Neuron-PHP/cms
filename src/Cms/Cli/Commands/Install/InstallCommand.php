<?php

namespace Neuron\Cms\Cli\Commands\Install;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Models\User;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Yaml;
use Neuron\Patterns\Registry;
use Neuron\Cms\Enums\UserRole;
use Neuron\Cms\Enums\UserStatus;

/**
 * Install the CMS admin UI into the project
 *
 * Publishes view templates, creates directories, and sets up initial admin user
 */
class InstallCommand extends Command
{

	private string $_projectPath;
	private string $_componentPath;
	private array $_messages = [];

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
	public function execute( array $parameters = [] ): int
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Neuron CMS - Installation            ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		// Check if already installed
		if( $this->isAlreadyInstalled() )
		{
			$this->output->warning( "Admin UI appears to be already installed." );
			$this->output->writeln( "Resources directory exists: resources/views/admin/" );
			$this->output->writeln( "" );

			if( !$this->confirm( "Do you want to reinstall? This will overwrite existing files", false ) )
			{
				$this->output->error( "Installation cancelled." );
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
			'copyMigrations' => 'Copying database migrations...',
		];

		foreach( $steps as $method => $message )
		{
			$this->output->writeln( $message );

			if( !$this->$method() )
			{
				$this->output->error( "Installation failed!" );
				return 1;
			}
		}

		// Ask to run migration
		$this->output->writeln( "" );
		if( $this->confirm( "Would you like to run the migration now?", true ) )
		{
			if( !$this->runMigration() )
			{
				$this->output->error( "Migration failed!" );
				$this->output->info( "You can run it manually with: php neuron db:migrate" );
				return 1;
			}

			// Seed default data after successful migration
			$this->output->writeln( "" );
			$this->output->writeln( "Seeding default data..." );
			if( !$this->seedDefaultData() )
			{
				$this->output->warning( "Failed to seed default data" );
				$this->output->info( "You can create default categories manually in the admin panel" );
			}
		}
		else
		{
			$this->output->info( "Remember to run migration with: php neuron db:migrate" );
		}

		// Display summary
		$this->output->success( "Installation complete!" );
		$this->displaySummary();

		// Create first admin user
		$this->output->writeln( "" );
		if( $this->confirm( "Would you like to create an admin user now?", true ) )
		{
			$this->createAdminUser();
		}
		else
		{
			$this->output->info( "You can create an admin user later with: php neuron cms:user:create" );
		}

		return 0;
	}

	/**
	 * Check if CMS is already installed
	 */
	protected function isAlreadyInstalled(): bool
	{
		return is_dir( $this->_projectPath . '/resources/views/admin' );
	}

	/**
	 * Create necessary directories
	 */
	protected function createDirectories(): bool
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
			'/resources/views/member',
			'/resources/views/member/dashboard',
			'/resources/views/member/profile',

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
			'/storage/uploads',
			'/storage/uploads/temp',

			// Database directories
			'/db',
			'/db/migrate',
			'/db/seed',

			// Configuration directory
			'/config',
		];

		foreach( $directories as $dir )
		{
			$path = $this->_projectPath . $dir;

			if( !is_dir( $path ) )
			{
				if( !mkdir( $path, 0755, true ) )
				{
					$this->output->error( "  Failed to create: $dir" );
					return false;
				}

				$this->_messages[] = "Created: $dir";
			}
		}

		return true;
	}

	/**
	 * Publish view templates
	 */
	protected function publishViews(): bool
	{
		// Copy all view directories
		$viewDirs = [ 'admin', 'auth', 'blog', 'content', 'emails', 'home', 'http_codes', 'layouts', 'member' ];

		foreach( $viewDirs as $dir )
		{
			$viewSource = $this->_componentPath . '/resources/views/' . $dir;
			$viewDest = $this->_projectPath . '/resources/views/' . $dir;

			if( !is_dir( $viewSource ) )
			{
				$this->output->warning( "  Source views not found at: $viewSource" );
				continue; // Skip if directory doesn't exist
			}

			// Copy all view files recursively
			if( !$this->copyDirectory( $viewSource, $viewDest ) )
			{
				$this->output->error( "  Failed to copy views from: $dir" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Publish initializers
	 */
	protected function publishInitializers(): bool
	{
		$initializerSource = $this->_componentPath . '/resources/app/Initializers';
		$initializerDest = $this->_projectPath . '/app/Initializers';

		if( !is_dir( $initializerSource ) )
		{
			$this->output->warning( "  Source initializers not found at: $initializerSource" );
			return true; // Don't fail, initializers might not exist yet
		}

		// Copy all initializer files
		return $this->copyDirectory( $initializerSource, $initializerDest );
	}

	/**
	 * Create route configuration
	 */
	protected function createRouteConfig(): bool
	{
		$routeFile = $this->_projectPath . '/config/routes.yaml';
		$resourceFile = $this->_componentPath . '/resources/config/routes.yaml';

		// If routes.yaml doesn't exist, copy from resources
		if( !file_exists( $routeFile ) && file_exists( $resourceFile ) )
		{
			if( copy( $resourceFile, $routeFile ) )
			{
				$this->_messages[] = "Created: config/routes.yaml";
				return true;
			}

			$this->output->error( "  Failed to create routes.yaml" );
			return false;
		}

		// If routes.yaml exists, check if it has admin routes
		if( file_exists( $routeFile ) )
		{
			$content = file_get_contents( $routeFile );

			if( strpos( $content, '/admin/dashboard' ) === false )
			{
				$this->_messages[] = "⚠️  Please add admin routes to config/routes.yaml";
				$this->_messages[] = "   See resources/config/routes.yaml for reference";
			}
		}

		return true;
	}

	/**
	 * Create auth configuration
	 */
	protected function createAuthConfig(): bool
	{
		$authFile = $this->_projectPath . '/config/auth.yaml';
		$resourceFile = $this->_componentPath . '/resources/config/auth.yaml';

		// If auth.yaml doesn't exist, copy from resources
		if( !file_exists( $authFile ) && file_exists( $resourceFile ) )
		{
			if( copy( $resourceFile, $authFile ) )
			{
				$this->_messages[] = "Created: config/auth.yaml";
				return true;
			}

			$this->output->error( "  Failed to create auth.yaml" );
			return false;
		}

		return true;
	}

	/**
	 * Create public folder and copy all static assets
	 */
	protected function createPublicFiles(): bool
	{
		$publicDir = $this->_projectPath . '/public';

		// Create public directory if it doesn't exist
		if( !is_dir( $publicDir ) )
		{
			if( !mkdir( $publicDir, 0755, true ) )
			{
				$this->output->error( "  Failed to create public directory" );
				return false;
			}
		}

		// Copy all files from resources/public
		$sourceDir = $this->_componentPath . '/resources/public';

		if( !is_dir( $sourceDir ) )
		{
			$this->output->error( "  Source public directory not found" );
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
					$this->_messages[] = "Created: public/" . $file;
				}
				else
				{
					$this->output->error( "  Failed to copy: " . $file );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Copy directory recursively
	 */
	protected function copyDirectory( string $source, string $dest ): bool
	{
		if( !is_dir( $source ) )
		{
			return false;
		}

		if( !is_dir( $dest ) )
		{
			mkdir( $dest, 0755, true );
		}

		$items = scandir( $source );

		foreach( $items as $item )
		{
			if( $item === '.' || $item === '..' )
			{
				continue;
			}

			$sourcePath = $source . '/' . $item;
			$destPath = $dest . '/' . $item;

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
					$this->output->error( "  Failed to copy: $item" );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Setup database configuration
	 */
	protected function setupDatabase(): bool
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Database Configuration               ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		$choice = $this->choice(
			"Select database adapter:",
			[
				'sqlite' => 'SQLite - Recommended for development (zero config, single file)',
				'mysql' => 'MySQL - Recommended for production (proven scalability)',
				'pgsql' => 'PostgreSQL - Enterprise features (advanced querying)'
			],
			'sqlite'
		);

		$this->output->writeln( "\nAll databases support:" );
		$this->output->writeln( "  ✓ Foreign key constraints with cascade deletes" );
		$this->output->writeln( "  ✓ Automatic timestamp management" );
		$this->output->writeln( "  ✓ Full ACID transaction support" );
		$this->output->writeln( "" );

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

		// Optionally configure Cloudinary
		$cloudinaryConfig = $this->configureCloudinary();

		// Optionally configure Email
		$emailConfig = $this->configureEmail();

		// Merge and save complete configuration
		return $this->saveCompleteConfig( $config, $appConfig, $cloudinaryConfig, $emailConfig );
	}

	/**
	 * Configure application settings
	 */
	protected function configureApplication(): array
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Application Configuration            ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		// System timezone
		$defaultTimezone = date_default_timezone_get();
		$timezone = $this->prompt( "System timezone", $defaultTimezone );

		// Site configuration
		$this->output->writeln( "\n--- Site Information ---\n" );

		$siteName = $this->prompt( "Site name" );

		if( !$siteName )
		{
			$this->output->error( "Site name is required!" );
			return [];
		}

		$siteTitle = $this->prompt( "Site title (displayed in browser)", $siteName );
		$siteUrl = $this->prompt( "Site URL (e.g., https://example.com)" );

		if( !$siteUrl )
		{
			$this->output->error( "Site URL is required!" );
			return [];
		}

		$siteDescription = $this->prompt( "Site description (optional)", "" );

		$this->_messages[] = "Site: $siteName ($siteUrl)";
		$this->_messages[] = "Timezone: $timezone";

		return [
			'timezone' => $timezone,
			'siteName' => $siteName,
			'siteTitle' => $siteTitle,
			'siteUrl' => $siteUrl,
			'siteDescription' => $siteDescription
		];
	}

	/**
	 * Configure Cloudinary (optional)
	 */
	protected function configureCloudinary(): array
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Cloudinary Configuration (Optional)  ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		$this->output->writeln( "Cloudinary is a cloud-based image and video management service." );
		$this->output->writeln( "It's used for uploading, storing, and delivering media files." );
		$this->output->writeln( "" );
		$this->output->writeln( "Get a free account at: https://cloudinary.com" );
		$this->output->writeln( "Find credentials at: https://console.cloudinary.com/settings/general" );
		$this->output->writeln( "" );

		if( !$this->confirm( "Would you like to configure Cloudinary now?", false ) )
		{
			$this->output->info( "Skipping Cloudinary configuration." );
			$this->output->writeln( "You can add credentials later in config/neuron.yaml" );
			return [];
		}

		$this->output->writeln( "\n--- Cloudinary Credentials ---\n" );

		$cloudName = $this->prompt( "Cloud name (from Cloudinary dashboard)" );

		if( !$cloudName )
		{
			$this->output->warning( "Cloud name is required for Cloudinary. Skipping configuration." );
			return [];
		}

		$apiKey = $this->prompt( "API key (from Cloudinary dashboard)" );

		if( !$apiKey )
		{
			$this->output->warning( "API key is required for Cloudinary. Skipping configuration." );
			return [];
		}

		$apiSecret = $this->secret( "API secret (from Cloudinary dashboard)" );

		if( !$apiSecret )
		{
			$this->output->warning( "API secret is required for Cloudinary. Skipping configuration." );
			return [];
		}

		$folder = $this->prompt( "Upload folder (optional)", "neuron-cms/images" );
		$maxFileSize = $this->prompt( "Max file size in bytes", "5242880" );  // 5MB default

		$this->_messages[] = "Cloudinary: $cloudName (folder: $folder)";

		return [
			'cloudinary' => [
				'cloud_name' => $cloudName,
				'api_key' => $apiKey,
				'api_secret' => $apiSecret,
				'folder' => $folder,
				'max_file_size' => (int)$maxFileSize,
				'allowed_formats' => [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ]
			]
		];
	}

	/**
	 * Configure Email (optional)
	 */
	protected function configureEmail(): array
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Email Configuration (Optional)       ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		$this->output->writeln( "Email is used for:" );
		$this->output->writeln( "  • Email verification for new accounts" );
		$this->output->writeln( "  • Password reset requests" );
		$this->output->writeln( "  • User notifications and welcome emails" );
		$this->output->writeln( "" );

		if( !$this->confirm( "Would you like to configure email now?", false ) )
		{
			$this->output->info( "Skipping email configuration." );
			$this->output->writeln( "Email will be in test mode (emails logged, not sent)" );
			$this->output->writeln( "You can configure email later in config/neuron.yaml" );

			return [
				'email' => [
					'driver' => 'mail',
					'test_mode' => true,
					'from_address' => 'noreply@example.com',
					'from_name' => 'Neuron CMS'
				]
			];
		}

		$this->output->writeln( "\n--- Email Driver ---\n" );

		$driver = $this->choice(
			"Select email driver:",
			[
				'smtp' => 'SMTP - Recommended for production (Gmail, SendGrid, Mailgun, etc.)',
				'mail' => 'PHP mail() - Simple but less reliable (may go to spam)'
			],
			'smtp'
		);

		$config = [
			'driver' => $driver,
			'test_mode' => false
		];

		// SMTP-specific configuration
		if( $driver === 'smtp' )
		{
			$this->output->writeln( "\n--- SMTP Settings ---\n" );
			$this->output->writeln( "Common SMTP providers:" );
			$this->output->writeln( "  • Gmail: smtp.gmail.com:587 (TLS)" );
			$this->output->writeln( "  • SendGrid: smtp.sendgrid.net:587 (TLS)" );
			$this->output->writeln( "  • Mailgun: smtp.mailgun.org:587 (TLS)" );
			$this->output->writeln( "  • Amazon SES: email-smtp.[region].amazonaws.com:587 (TLS)" );
			$this->output->writeln( "" );

			$host = $this->prompt( "SMTP host (e.g., smtp.gmail.com)" );

			if( !$host )
			{
				$this->output->warning( "SMTP host is required. Falling back to test mode." );
				return [
					'email' => [
						'driver' => 'mail',
						'test_mode' => true,
						'from_address' => 'noreply@example.com',
						'from_name' => 'Neuron CMS'
					]
				];
			}

			$port = $this->prompt( "SMTP port", "587" );
			$encryption = $this->choice(
				"Encryption type:",
				[
					'tls' => 'TLS (recommended, port 587)',
					'ssl' => 'SSL (port 465)',
					'none' => 'None (not recommended)'
				],
				'tls'
			);

			$username = $this->prompt( "SMTP username (usually your email address)" );

			if( !$username )
			{
				$this->output->warning( "SMTP username is required. Falling back to test mode." );
				return [
					'email' => [
						'driver' => 'mail',
						'test_mode' => true,
						'from_address' => 'noreply@example.com',
						'from_name' => 'Neuron CMS'
					]
				];
			}

			$password = $this->secret( "SMTP password (or app-specific password)" );

			if( !$password )
			{
				$this->output->warning( "SMTP password is required. Falling back to test mode." );
				return [
					'email' => [
						'driver' => 'mail',
						'test_mode' => true,
						'from_address' => 'noreply@example.com',
						'from_name' => 'Neuron CMS'
					]
				];
			}

			$config['host'] = $host;
			$config['port'] = (int)$port;
			$config['username'] = $username;
			$config['password'] = $password;
			$config['encryption'] = $encryption;

			$this->_messages[] = "Email: SMTP ($host:$port)";
		}
		else
		{
			$this->_messages[] = "Email: PHP mail()";
		}

		// Common configuration
		$this->output->writeln( "\n--- Sender Information ---\n" );

		$fromAddress = $this->prompt( "From email address", "noreply@example.com" );
		$fromName = $this->prompt( "From name", "Neuron CMS" );

		$config['from_address'] = $fromAddress;
		$config['from_name'] = $fromName;

		// Test mode option
		$this->output->writeln( "" );
		$testMode = $this->confirm( "Enable test mode? (emails logged, not sent - useful for development)", false );

		if( $testMode )
		{
			$config['test_mode'] = true;
			$this->_messages[] = "Email test mode: ENABLED (emails will be logged, not sent)";
		}

		return [
			'email' => $config
		];
	}

	/**
	 * Save complete configuration with all required sections
	 */
	protected function saveCompleteConfig( array $databaseConfig, array $appConfig, array $cloudinaryConfig = [], array $emailConfig = [] ): bool
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
			'routing' => [
				'controller_paths' => [
					[
						'path' => 'app/Controllers',
						'namespace' => 'App\\Controllers'
					],
					[
						'path' => 'vendor/neuron-php/cms/src/Cms/Controllers',
						'namespace' => 'Neuron\\Cms\\Controllers'
					]
				]
			],
			'system' => [
				'timezone' => $appConfig['timezone'],
				'base_path' => $this->_projectPath
			],
			'site' => [
				'name' => $appConfig['siteName'],
				'title' => $appConfig['siteTitle'],
				'url' => $appConfig['siteUrl'],
				'description' => $appConfig['siteDescription']
			],
			'cache' => [
				'enabled' => false,
				'storage' => 'file',
				'path' => 'cache/views',
				'ttl' => 3600
			]
		];

		// Merge database configuration
		$config = array_merge( $config, $databaseConfig );

		// Merge Cloudinary configuration if provided
		if( !empty( $cloudinaryConfig ) )
		{
			$config = array_merge( $config, $cloudinaryConfig );
		}

		// Merge Email configuration if provided
		if( !empty( $emailConfig ) )
		{
			$config = array_merge( $config, $emailConfig );
		}

		// Save configuration
		return $this->saveConfig( $config );
	}

	/**
	 * Configure SQLite
	 */
	protected function configureSqlite(): array
	{
		$this->output->writeln( "\n--- SQLite Configuration ---\n" );

		$dbPath = $this->prompt( "Database file path", "storage/database.sqlite3" );

		// Make path absolute if relative
		if( !empty( $dbPath ) && $dbPath[0] !== '/' )
		{
			$dbPath = $this->_projectPath . '/' . $dbPath;
		}

		// Create directory if needed
		$dir = dirname( $dbPath );
		if( !is_dir( $dir ) )
		{
			if( !mkdir( $dir, 0755, true ) )
			{
				$this->output->error( "Failed to create directory: $dir" );
				return [];
			}
		}

		$this->_messages[] = "Database: SQLite ($dbPath)";

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
	protected function configureMysql(): array
	{
		$this->output->writeln( "\n--- MySQL Configuration ---\n" );

		$host = $this->prompt( "Host", "localhost" );
		$port = $this->prompt( "Port", "3306" );
		$name = $this->prompt( "Database name" );

		if( !$name )
		{
			$this->output->error( "Database name is required!" );
			return [];
		}

		$user = $this->prompt( "Database username" );

		if( !$user )
		{
			$this->output->error( "Username is required!" );
			return [];
		}

		$pass = $this->secret( "Database password" );
		$charset = $this->prompt( "Character set (utf8mb4 recommended)", "utf8mb4" );

		$this->_messages[] = "Database: MySQL ($host:$port/$name)";

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
	protected function configurePostgresql(): array
	{
		$this->output->writeln( "\n--- PostgreSQL Configuration ---\n" );

		$host = $this->prompt( "Host", "localhost" );
		$port = $this->prompt( "Port", "5432" );
		$name = $this->prompt( "Database name" );

		if( !$name )
		{
			$this->output->error( "Database name is required!" );
			return [];
		}

		$user = $this->prompt( "Database username" );

		if( !$user )
		{
			$this->output->error( "Username is required!" );
			return [];
		}

		$pass = $this->secret( "Database password" );

		$this->_messages[] = "Database: PostgreSQL ($host:$port/$name)";

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
	protected function saveConfig( array $config ): bool
	{
		$configFile = $this->_projectPath . '/config/neuron.yaml';

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
		$finalConfig = array_merge( $existingConfig, $config );

		// Convert to YAML manually (simple approach)
		$yamlContent = $this->arrayToYaml( $finalConfig );

		if( file_put_contents( $configFile, $yamlContent ) === false )
		{
			$this->output->error( "Failed to save configuration file!" );
			return false;
		}

		$this->_messages[] = "Created: config/neuron.yaml";
		return true;
	}

	/**
	 * Convert array to YAML format (simple implementation)
	 */
	protected function arrayToYaml( array $data, int $indent = 0 ): string
	{
		$yaml = '';
		$indentStr = str_repeat( '  ', $indent );

		foreach( $data as $key => $value )
		{
			if( is_array( $value ) )
			{
				// Check if this is a numeric array (list)
				if( array_keys( $value ) === range( 0, count( $value ) - 1 ) )
				{
					// This is a list, output the key first if not numeric
					if( !is_int( $key ) )
					{
						$yaml .= $indentStr . $key . ":\n";
					}

					// Format list items with dashes
					foreach( $value as $item )
					{
						if( is_array( $item ) )
						{
							$yaml .= $indentStr . "  -\n";
							$yaml .= $this->arrayToYaml( $item, $indent + 2 );
						}
						else
						{
							$yaml .= $indentStr . "  - " . $this->yamlValue( $item ) . "\n";
						}
					}
				}
				else
				{
					// This is an associative array
					$yaml .= $indentStr . $key . ":\n";
					$yaml .= $this->arrayToYaml( $value, $indent + 1 );
				}
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
	protected function yamlValue( $value ): string
	{
		if( is_bool( $value ) )
		{
			return $value ? 'true' : 'false';
		}

		if( is_int( $value ) || is_float( $value ) )
		{
			return (string)$value;
		}

		if( is_string( $value ) && ( strpos( $value, ' ' ) !== false || strpos( $value, ':' ) !== false ) )
		{
			return '"' . addslashes( $value ) . '"';
		}

		return (string)$value;
	}

	/**
	 * Copy database migrations from component to project
	 */
	protected function copyMigrations(): bool
	{
		$migrationsDir = $this->_projectPath . '/db/migrate';
		$componentMigrationsDir = $this->_componentPath . '/resources/database/migrate';

		// Create migrations directory if it doesn't exist
		if( !is_dir( $migrationsDir ) )
		{
			if( !mkdir( $migrationsDir, 0755, true ) )
			{
				$this->output->error( "Failed to create migrations directory!" );
				return false;
			}
		}

		// Check if component migrations directory exists
		if( !is_dir( $componentMigrationsDir ) )
		{
			$this->output->error( "Component migrations directory not found at: $componentMigrationsDir" );
			return false;
		}

		// Copy all migration files
		$files = glob( $componentMigrationsDir . '/*.php' );

		if( empty( $files ) )
		{
			$this->output->warning( "No migration files found in component" );
			return true;
		}

		foreach( $files as $sourceFile )
		{
			$fileName = basename( $sourceFile );
			$destFile = $migrationsDir . '/' . $fileName;

			// Skip if file already exists
			if( file_exists( $destFile ) )
			{
				$this->_messages[] = "Migration already exists: db/migrate/$fileName";
				continue;
			}

			if( copy( $sourceFile, $destFile ) )
			{
				$this->_messages[] = "Copied migration: db/migrate/$fileName";
			}
			else
			{
				$this->output->error( "Failed to copy migration: $fileName" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Run the migration
	 */
	protected function runMigration(): bool
	{
		$this->output->writeln( "\nRunning migration...\n" );

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
	 * Display installation summary
	 */
	protected function displaySummary(): void
	{
		$this->output->writeln( "Installation Summary:" );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );

		foreach( $this->_messages as $message )
		{
			$this->output->writeln( "  $message" );
		}

		$this->output->writeln( "\nNext Steps:" );
		$this->output->writeln( "  1. Create an admin user (prompted below)" );
		$this->output->writeln( "  2. Review config/routes.yaml for admin routes" );
		$this->output->writeln( "  3. Start your server and visit /login" );
		$this->output->writeln( "  4. Access admin at /admin/dashboard" );
	}

	/**
	 * Create admin user interactively
	 */
	protected function createAdminUser(): bool
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Create Admin User                    ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		try
		{
			$settings = Registry::getInstance()->get( 'Settings' );

			if( !$settings )
			{
				$this->output->error( "Application not initialized: Settings not found in Registry" );
				$this->output->writeln( "This is a configuration error - the application should load settings into the Registry" );
				return false;
			}

			$repository = new DatabaseUserRepository( $settings );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Database connection failed: " . $e->getMessage() );
			return false;
		}

		$hasher = new PasswordHasher();

		// Get username
		$username = $this->prompt( "Username (alphanumeric, 3-50 chars)", "admin" );

		// Check if user exists
		if( $repository->findByUsername( $username ) )
		{
			$this->output->warning( "User '$username' already exists!" );
			$this->output->writeln( "You can manage users with: php neuron cms:user:list" );
			return true;
		}

		// Get email
		$email = $this->prompt( "Email address", "admin@example.com" );

		// Get password
		$this->output->writeln( "Password requirements: min 8 chars, uppercase, lowercase, number, special char" );
		$password = $this->secret( "Password" );

		if( strlen( $password ) < 8 )
		{
			$this->output->error( "Password must be at least 8 characters long!" );
			return false;
		}

		// Validate password
		if( !$hasher->meetsRequirements( $password ) )
		{
			$this->output->error( "Password does not meet requirements:" );

			foreach( $hasher->getValidationErrors( $password ) as $error )
			{
				$this->output->writeln( "  - $error" );
			}

			$this->output->writeln( "" );
			return false;
		}

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $hasher->hash( $password ) );
		$user->setRole( UserRole::ADMIN->value );
		$user->setStatus( UserStatus::ACTIVE->value );
		$user->setEmailVerified( true );

		try
		{
			$repository->create( $user );

			$this->output->success( "Admin user created:" );
			$this->output->writeln( "  Username: " . $user->getUsername() );
			$this->output->writeln( "  Email: " . $user->getEmail() );
			$this->output->writeln( "  Role: " . $user->getRole() );
			$this->output->writeln( "\n🚀 You can now login at: http://localhost:8000/login" );

			return true;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error creating user: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Seed default data after migration
	 */
	protected function seedDefaultData(): bool
	{
		try
		{
			$settings = Registry::getInstance()->get( 'Settings' );

			if( !$settings )
			{
				$this->output->error( "Settings not found in Registry" );
				return false;
			}

			$success = true;

			// Seed default event category
			$eventCategoryRepository = new DatabaseEventCategoryRepository( $settings );
			$existingEventCategory = $eventCategoryRepository->findBySlug( 'general-events' );

			if( !$existingEventCategory )
			{
				$eventCategory = new EventCategory();
				$eventCategory->setName( 'General Events' );
				$eventCategory->setSlug( 'general-events' );
				$eventCategory->setColor( '#3b82f6' );
				$eventCategory->setDescription( 'General community events and activities' );

				$eventCategoryRepository->create( $eventCategory );
				$this->output->success( "  Created default event category: General Events" );
			}
			else
			{
				$this->output->info( "  Default event category already exists" );
			}

			// Seed default post category
			$postCategoryRepository = new DatabaseCategoryRepository( $settings );
			$existingPostCategory = $postCategoryRepository->findBySlug( 'general' );

			if( !$existingPostCategory )
			{
				$postCategory = new Category();
				$postCategory->setName( 'General' );
				$postCategory->setSlug( 'general' );
				$postCategory->setDescription( 'General blog posts and articles' );

				$postCategoryRepository->create( $postCategory );
				$this->output->success( "  Created default post category: General" );
			}
			else
			{
				$this->output->info( "  Default post category already exists" );
			}

			return $success;
		}
		catch( \Exception $e )
		{
			$this->output->error( "  Error seeding default data: " . $e->getMessage() );
			return false;
		}
	}

}
