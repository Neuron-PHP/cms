<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

/**
 * Create a new user
 */
class CreateCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:user:create';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Create a new user';
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
		$this->output( "║  Neuron CMS - Create User            ║" );
		$this->output( "╚═══════════════════════════════════════╝\n" );

		// Load database configuration
		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return self::FAILURE;
		}

		$hasher = new PasswordHasher();

		// Get username
		$username = $this->prompt( "Enter username: " );
		$username = trim( $username );

		if( empty( $username ) )
		{
			$this->output( "\n❌ Error: Username is required!\n" );
			return self::FAILURE;
		}

		// Check if user exists
		if( $repository->findByUsername( $username ) )
		{
			$this->output( "\n❌ Error: User '$username' already exists!\n" );
			return self::FAILURE;
		}

		// Get email
		$email = $this->prompt( "Enter email: " );
		$email = trim( $email );

		if( empty( $email ) || !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->output( "\n❌ Error: Valid email is required!\n" );
			return self::FAILURE;
		}

		// Check if email exists
		if( $repository->findByEmail( $email ) )
		{
			$this->output( "\n❌ Error: Email '$email' is already in use!\n" );
			return self::FAILURE;
		}

		// Get password
		$password = $this->prompt( "Enter password (min 8 characters): " );

		if( strlen( $password ) < 8 )
		{
			$this->output( "\n❌ Error: Password must be at least 8 characters long!\n" );
			return self::FAILURE;
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
			return self::FAILURE;
		}

		// Get role
		$this->output( "\nAvailable roles:" );
		$this->output( "  1. Admin (full access)" );
		$this->output( "  2. Editor (manage all content)" );
		$this->output( "  3. Author (manage own content)" );
		$this->output( "  4. Subscriber (read-only)" );

		$roleChoice = $this->prompt( "\nSelect role (1-4) [3]: " );
		$roleChoice = trim( $roleChoice ) ?: '3';

		$roles = [
			'1' => User::ROLE_ADMIN,
			'2' => User::ROLE_EDITOR,
			'3' => User::ROLE_AUTHOR,
			'4' => User::ROLE_SUBSCRIBER,
		];

		$role = $roles[ $roleChoice ] ?? User::ROLE_AUTHOR;

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $hasher->hash( $password ) );
		$user->setRole( $role );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );

		try
		{
			$repository->create( $user );

			$this->output( "\n✅ Success! User created:" );
			$this->output( "  ID: " . $user->getId() );
			$this->output( "  Username: " . $user->getUsername() );
			$this->output( "  Email: " . $user->getEmail() );
			$this->output( "  Role: " . $user->getRole() );
			$this->output( "" );

			return self::SUCCESS;
		}
		catch( \Exception $e )
		{
			$this->output( "\n❌ Error creating user: " . $e->getMessage() . "\n" );
			return self::FAILURE;
		}
	}

	/**
	 * Get user repository
	 */
	private function getUserRepository(): ?DatabaseUserRepository
	{
		$configFile = getcwd() . '/config/config.yaml';

		if( !file_exists( $configFile ) )
		{
			$this->output( "\n❌ Configuration file not found at: $configFile" );
			$this->output( "Please run: php neuron cms:install\n" );
			return null;
		}

		try
		{
			$yaml = new Yaml( $configFile );
			$settings = new SettingManager( $yaml );

			// Get database configuration
			$dbConfig = $this->getDatabaseConfig( $settings );

			if( !$dbConfig )
			{
				$this->output( "\n❌ Database configuration not found!" );
				$this->output( "Please run: php neuron cms:install\n" );
				return null;
			}

			return new DatabaseUserRepository( $dbConfig );
		}
		catch( \Exception $e )
		{
			$this->output( "\n❌ Database connection failed: " . $e->getMessage() . "\n" );
			return null;
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
