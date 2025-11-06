<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

/**
 * Delete a user
 */
class DeleteCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:user:delete';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Delete a user';
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
		$identifier = $Parameters['identifier'] ?? null;

		if( !$identifier )
		{
			$this->output( "\n❌ Error: Please provide a user ID or username.\n" );
			$this->output( "Usage: php neuron cms:user:delete <id or username>\n" );
			return self::FAILURE;
		}

		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return self::FAILURE;
		}

		// Find user by ID or username
		$user = null;

		if( is_numeric( $identifier ) )
		{
			$user = $repository->findById( (int)$identifier );
		}
		else
		{
			$user = $repository->findByUsername( $identifier );
		}

		if( !$user )
		{
			$this->output( "\n❌ Error: User '$identifier' not found.\n" );
			return self::FAILURE;
		}

		// Display user info
		$this->output( "\n⚠️  You are about to delete the following user:" );
		$this->output( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
		$this->output( "  ID:       " . $user->getId() );
		$this->output( "  Username: " . $user->getUsername() );
		$this->output( "  Email:    " . $user->getEmail() );
		$this->output( "  Role:     " . $user->getRole() );
		$this->output( "  Status:   " . $user->getStatus() );
		$this->output( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" );

		// Confirm deletion
		$response = $this->prompt( "Are you sure you want to delete this user? Type 'DELETE' to confirm: " );

		if( trim( $response ) !== 'DELETE' )
		{
			$this->output( "\n❌ Deletion cancelled.\n" );
			return self::FAILURE;
		}

		// Delete user
		try
		{
			if( $repository->delete( $user->getId() ) )
			{
				$this->output( "\n✅ User deleted successfully.\n" );
				return self::SUCCESS;
			}

			$this->output( "\n❌ Failed to delete user.\n" );
			return self::FAILURE;
		}
		catch( \Exception $e )
		{
			$this->output( "\n❌ Error: " . $e->getMessage() . "\n" );
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
