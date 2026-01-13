<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Patterns\Registry;

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
	public function execute( array $parameters = [] ): int
	{
		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return 1;
		}

		$users = $repository->all();

		if( empty( $users ) )
		{
			$this->output->info( "No users found." );
			return 0;
		}

		$this->output->writeln( "" );
		$this->displayUsersTable( $users );
		$this->output->writeln( "\nTotal users: " . count( $users ) );

		return 0;
	}

	/**
	 * Display users in a formatted table
	 */
	private function displayUsersTable( array $users ): void
	{
		// Calculate column widths
		$idWidth = 4;
		$usernameWidth = 20;
		$emailWidth = 30;
		$roleWidth = 12;
		$statusWidth = 10;
		$createdWidth = 19;

		// Header
		$this->output->writeln( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );
		$this->output->writeln(
			$this->pad( 'ID', $idWidth ) . ' │ ' .
			$this->pad( 'Username', $usernameWidth ) . ' │ ' .
			$this->pad( 'Email', $emailWidth ) . ' │ ' .
			$this->pad( 'Role', $roleWidth ) . ' │ ' .
			$this->pad( 'Status', $statusWidth ) . ' │ ' .
			$this->pad( 'Created', $createdWidth )
		);
		$this->output->writeln( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );

		// Rows
		foreach( $users as $user )
		{
			$this->output->writeln(
				$this->pad( (string)$user->getId(), $idWidth ) . ' │ ' .
				$this->pad( $this->truncate( $user->getUsername(), $usernameWidth ), $usernameWidth ) . ' │ ' .
				$this->pad( $this->truncate( $user->getEmail(), $emailWidth ), $emailWidth ) . ' │ ' .
				$this->pad( $user->getRole(), $roleWidth ) . ' │ ' .
				$this->pad( $this->formatStatus( $user ), $statusWidth ) . ' │ ' .
				$this->pad( $user->getCreatedAt()->format( 'Y-m-d H:i:s' ), $createdWidth )
			);
		}

		$this->output->writeln( str_repeat( '─', $idWidth + $usernameWidth + $emailWidth + $roleWidth + $statusWidth + $createdWidth + 17 ) );
	}

	/**
	 * Format user status
	 */
	private function formatStatus( $user ): string
	{
		if( $user->isLockedOut() )
		{
			return '🔒 Locked';
		}

		return $user->getStatus();
	}

	/**
	 * Pad string to width
	 */
	private function pad( string $text, int $width ): string
	{
		return str_pad( $text, $width );
	}

	/**
	 * Truncate string to width
	 */
	private function truncate( string $text, int $width ): string
	{
		if( strlen( $text ) <= $width )
		{
			return $text;
		}

		return substr( $text, 0, $width - 3 ) . '...';
	}

	/**
	 * Get user repository
	 */
	protected function getUserRepository(): ?DatabaseUserRepository
	{
		try
		{
			$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

			if( !$settings )
			{
				$this->output->error( "Application not initialized: Settings not found in Registry" );
				$this->output->writeln( "This is a configuration error - the application should load settings into the Registry" );
				return null;
			}

			return new DatabaseUserRepository( $settings );
		}
		catch( \Exception $e )
		{
			$this->output->error( "Database connection failed: " . $e->getMessage() );
			return null;
		}
	}

}
