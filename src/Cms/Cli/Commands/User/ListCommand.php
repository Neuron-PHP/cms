<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

/**
 * List all users
 */
class ListCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:user:list';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'List all users';
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
		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return self::FAILURE;
		}

		$users = $repository->all();

		if( empty( $users ) )
		{
			$this->output( "\nℹ️  No users found.\n" );
			return self::SUCCESS;
		}

		$this->output( "\n" );
		$this->displayUsersTable( $users );
		$this->output( "\nTotal users: " . count( $users ) . "\n" );

		return self::SUCCESS;
	}

	/**
	 * Display users in a formatted table
	 */
	private function displayUsersTable( array $Users ): void
	{
		// Calculate column widths
		$idWidth = 4;
		$usernameWidth = 20;
		$emailWidth = 30;
		$roleWidth = 12;
		$statusWidth = 10;
		$createdWidth = 19;

		// Header
		$this->output( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );
		$this->output(
			$this->pad( 'ID', $idWidth ) . ' │ ' .
			$this->pad( 'Username', $usernameWidth ) . ' │ ' .
			$this->pad( 'Email', $emailWidth ) . ' │ ' .
			$this->pad( 'Role', $roleWidth ) . ' │ ' .
			$this->pad( 'Status', $statusWidth ) . ' │ ' .
			$this->pad( 'Created', $createdWidth )
		);
		$this->output( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );

		// Rows
		foreach( $Users as $user )
		{
			$this->output(
				$this->pad( (string)$user->getId(), $idWidth ) . ' │ ' .
				$this->pad( $this->truncate( $user->getUsername(), $usernameWidth ), $usernameWidth ) . ' │ ' .
				$this->pad( $this->truncate( $user->getEmail(), $emailWidth ), $emailWidth ) . ' │ ' .
				$this->pad( $user->getRole(), $roleWidth ) . ' │ ' .
				$this->pad( $this->formatStatus( $user ), $statusWidth ) . ' │ ' .
				$this->pad( $user->getCreatedAt()->format( 'Y-m-d H:i:s' ), $createdWidth )
			);
		}

		$this->output( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );
	}

	/**
	 * Format user status
	 */
	private function formatStatus( $User ): string
	{
		if( $User->isLockedOut() )
		{
			return '🔒 Locked';
		}

		return $User->getStatus();
	}

	/**
	 * Pad string to width
	 */
	private function pad( string $Text, int $Width ): string
	{
		return str_pad( $Text, $Width );
	}

	/**
	 * Truncate string to width
	 */
	private function truncate( string $Text, int $Width ): string
	{
		if( strlen( $Text ) <= $Width )
		{
			return $Text;
		}

		return substr( $Text, 0, $Width - 3 ) . '...';
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
	 * Output message
	 */
	private function output( string $Message ): void
	{
		echo $Message . "\n";
	}
}
