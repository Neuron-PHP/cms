<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Patterns\Registry;

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
	public function execute( array $parameters = [] ): int
	{
		$identifier = $parameters['identifier'] ?? null;

		if( !$identifier )
		{
			$this->output->error( "Please provide a user ID or username." );
			$this->output->writeln( "Usage: php neuron cms:user:delete <id or username>" );
			return 1;
		}

		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return 1;
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
			$this->output->error( "User '$identifier' not found." );
			return 1;
		}

		// Display user info
		$this->output->warning( "You are about to delete the following user:" );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
		$this->output->writeln( "  ID:       " . $user->getId() );
		$this->output->writeln( "  Username: " . $user->getUsername() );
		$this->output->writeln( "  Email:    " . $user->getEmail() );
		$this->output->writeln( "  Role:     " . $user->getRole() );
		$this->output->writeln( "  Status:   " . $user->getStatus() );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );

		// Confirm deletion
		$response = $this->prompt( "Are you sure you want to delete this user? Type 'DELETE' to confirm: " );

		if( trim( $response ) !== 'DELETE' )
		{
			$this->output->error( "Deletion cancelled." );
			return 1;
		}

		// Delete user
		try
		{
			if( $repository->delete( $user->getId() ) )
			{
				$this->output->success( "User deleted successfully." );
				return 0;
			}

			$this->output->error( "Failed to delete user." );
			return 1;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error: " . $e->getMessage() );
			return 1;
		}
	}

	/**
	 * Get user repository
	 */
	protected function getUserRepository(): ?DatabaseUserRepository
	{
		try
		{
			$settings = Registry::getInstance()->get( 'Settings' );

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
