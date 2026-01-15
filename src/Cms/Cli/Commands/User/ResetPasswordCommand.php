<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Patterns\Registry;

/**
 * Reset user password
 */
class ResetPasswordCommand extends Command
{

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:user:reset-password';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Reset a user\'s password';
	}

	/**
	 * Configure the command
	 */
	public function configure(): void
	{
		$this->addOption( 'username', 'u', true, 'Username of the user' );
		$this->addOption( 'email', 'e', true, 'Email of the user' );
	}

	/**
	 * Execute the command
	 */
	public function execute( array $parameters = [] ): int
	{
		$this->output->writeln( "\n╔═══════════════════════════════════════╗" );
		$this->output->writeln( "║  Neuron CMS - Reset User Password    ║" );
		$this->output->writeln( "╚═══════════════════════════════════════╝\n" );

		// Load database configuration
		$repository = $this->getUserRepository();
		if( !$repository )
		{
			return 1;
		}

		$hasher = new PasswordHasher();

		// Get username or email from options or prompt
		$username = $this->input->getOption( 'username' );
		$email = $this->input->getOption( 'email' );

		// If neither provided, prompt for identifier
		if( !$username && !$email )
		{
			$identifier = $this->prompt( "Enter username or email: " );
			$identifier = trim( $identifier );

			if( empty( $identifier ) )
			{
				$this->output->error( "Username or email is required!" );
				return 1;
			}

			// Determine if it's an email or username
			if( filter_var( $identifier, FILTER_VALIDATE_EMAIL ) )
			{
				$email = $identifier;
			}
			else
			{
				$username = $identifier;
			}
		}

		// Find the user
		$user = null;
		if( $username )
		{
			$user = $repository->findByUsername( $username );
			if( !$user )
			{
				$this->output->error( "User '$username' not found!" );
				return 1;
			}
		}
		elseif( $email )
		{
			$user = $repository->findByEmail( $email );
			if( !$user )
			{
				$this->output->error( "User with email '$email' not found!" );
				return 1;
			}
		}

		// Display user info
		$this->output->writeln( "User found:" );
		$this->output->writeln( "  ID: " . $user->getId() );
		$this->output->writeln( "  Username: " . $user->getUsername() );
		$this->output->writeln( "  Email: " . $user->getEmail() );
		$this->output->writeln( "  Role: " . $user->getRole() );
		$this->output->writeln( "" );

		// Confirm action
		$confirm = $this->prompt( "Reset password for this user? (yes/no) [no]: " );
		if( strtolower( trim( $confirm ) ) !== 'yes' )
		{
			$this->output->warning( "Password reset cancelled." );
			return 0;
		}

		// Get new password
		$password = $this->secret( "\nEnter new password (min 8 characters): " );

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

		// Confirm password
		$confirmPassword = $this->secret( "Confirm new password: " );

		if( $password !== $confirmPassword )
		{
			$this->output->error( "Passwords do not match!" );
			return 1;
		}

		// Update password
		try
		{
			$user->setPasswordHash( $hasher->hash( $password ) );
			$user->setUpdatedAt( new \DateTimeImmutable() );

			// Clear any lockout
			$user->setFailedLoginAttempts( 0 );
			$user->setLockedUntil( null );

			$success = $repository->update( $user );

			if( !$success )
			{
				$this->output->error( "Failed to update password in database" );
				return 1;
			}

			$this->output->success( "Password reset successfully for user: " . $user->getUsername() );
			$this->output->writeln( "" );

			return 0;
		}
		catch( \Exception $e )
		{
			$this->output->error( "Error resetting password: " . $e->getMessage() );
			return 1;
		}
	}

	/**
	 * Get user repository
	 *
	 * Protected to allow mocking in tests
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
