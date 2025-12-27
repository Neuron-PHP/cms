<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Patterns\Registry;
use Neuron\Cms\Enums\UserRole;
use Neuron\Cms\Enums\UserStatus;

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
	public function execute( array $parameters = [] ): int
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Neuron CMS - Create User             ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		// Load database configuration
		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return 1;
		}

		$hasher = new PasswordHasher();

		// Get username
		$username = $this->prompt( "Enter username: " );
		$username = trim( $username );

		if( empty( $username ) )
		{
			$this->output->error( "Username is required!" );
			return 1;
		}

		// Check if user exists
		if( $repository->findByUsername( $username ) )
		{
			$this->output->error( "User '$username' already exists!" );
			return 1;
		}

		// Get email
		$email = $this->prompt( "Enter email: " );
		$email = trim( $email );

		if( empty( $email ) || !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			$this->output->error( "Valid email is required!" );
			return 1;
		}

		// Check if email exists
		if( $repository->findByEmail( $email ) )
		{
			$this->output->error( "Email '$email' is already in use!" );
			return 1;
		}

		// Get password
		$password = $this->prompt( "Enter password (min 8 characters): " );

		if( strlen( $password ) < 8 )
		{
			$this->output->error( "Password must be at least 8 characters long!" );
			return 1;
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
			return 1;
		}

		// Get role
		$this->output->writeln( "\nAvailable roles:" );
		$this->output->writeln( "  1. Admin (full access)" );
		$this->output->writeln( "  2. Editor (manage all content)" );
		$this->output->writeln( "  3. Author (manage own content)" );
		$this->output->writeln( "  4. Subscriber (read-only)" );

		$roleChoice = $this->prompt( "\nSelect role (1-4) [3]: " );
		$roleChoice = trim( $roleChoice ) ?: '3';

		$roles = [
			'1' => UserRole::ADMIN->value,
			'2' => UserRole::EDITOR->value,
			'3' => UserRole::AUTHOR->value,
			'4' => UserRole::SUBSCRIBER->value,
		];

		$role = $roles[ $roleChoice ] ?? UserRole::AUTHOR->value;

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $hasher->hash( $password ) );
		$user->setRole( $role );
		$user->setStatus( UserStatus::ACTIVE->value );
		$user->setEmailVerified( true );

		try
		{
			$repository->create( $user );

			$this->output->success( "User created:" );
			$this->output->writeln( "  ID: " . $user->getId() );
			$this->output->writeln( "  Username: " . $user->getUsername() );
			$this->output->writeln( "  Email: " . $user->getEmail() );
			$this->output->writeln( "  Role: " . $user->getRole() );
			$this->output->writeln( "" );

			return 0;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error creating user: " . $e->getMessage() );
			return 1;
		}
	}

	/**
	 * Get user repository
	 */
	private function getUserRepository(): ?DatabaseUserRepository
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

	/**
	 * Prompt for user input
	 */
	private function prompt( string $message ): string
	{
		$this->output->write( $message, false );
		return trim( fgets( STDIN ) );
	}
}
