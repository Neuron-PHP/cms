<?php

namespace Neuron\Cms\Cli\Commands\User;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Cli\IO\IInputReader;
use Neuron\Cms\Cli\IO\StdinInputReader;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Patterns\Container\IContainer;

/**
 * Delete a user command - Refactored for better testability.
 *
 * Key improvements:
 * - Constructor dependency injection
 * - No Registry dependencies
 * - Testable user input via IInputReader
 * - Clear separation of concerns
 * - Easy to mock in tests
 */
class DeleteCommand extends Command
{
	private IUserRepository $userRepository;
	private IInputReader $inputReader;

	/**
	 * @param IUserRepository|null $userRepository Injected or resolved from container
	 * @param IInputReader|null $inputReader Injected or defaults to STDIN reader
	 * @param IContainer|null $container For resolving dependencies if not provided
	 */
	public function __construct(
		?IUserRepository $userRepository = null,
		?IInputReader $inputReader = null,
		?IContainer $container = null
	) {
		// Use injected dependencies or resolve from container
		$this->userRepository = $userRepository ?? $container?->get( IUserRepository::class );
		$this->inputReader = $inputReader;

		// Initialize parent
		parent::__construct();
	}

	/**
	 * Setter for dependency injection (called by DI container).
	 *
	 * @param IUserRepository $repository
	 * @return void
	 */
	public function setUserRepository( IUserRepository $repository ): void
	{
		$this->userRepository = $repository;
	}

	/**
	 * Setter for input reader (called by DI container or tests).
	 *
	 * @param IInputReader $reader
	 * @return void
	 */
	public function setInputReader( IInputReader $reader ): void
	{
		$this->inputReader = $reader;
	}

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
		return 'Delete a user by ID or username';
	}

	/**
	 * @inheritDoc
	 */
	public function configure(): void
	{
		$this->addArgument( 'identifier', true, 'User ID or username to delete' );
		$this->addOption( 'force', 'f', false, 'Skip confirmation prompt' );
	}

	/**
	 * Execute the command.
	 *
	 * @param array<string,mixed> $parameters
	 * @return int Exit code (0 = success, 1 = error)
	 */
	public function execute( array $parameters = [] ): int
	{
		// Ensure dependencies are available
		if( !$this->ensureDependencies() ) {
			return 1;
		}

		// Get identifier from parameters
		$identifier = $parameters['identifier'] ?? null;
		if( !$identifier ) {
			$this->output->error( "Please provide a user ID or username." );
			$this->showUsage();
			return 1;
		}

		// Find user
		$user = $this->findUser( $identifier );
		if( !$user ) {
			$this->output->error( "User '{$identifier}' not found." );
			return 1;
		}

		// Display user information
		$this->displayUserInfo( $user );

		// Confirm deletion unless --force flag is used
		$force = $parameters['force'] ?? false;
		if( !$force && !$this->confirmDeletion() ) {
			$this->output->error( "Deletion cancelled." );
			return 1;
		}

		// Perform deletion
		return $this->deleteUser( $user );
	}

	/**
	 * Ensure all required dependencies are available.
	 *
	 * @return bool True if all dependencies available, false otherwise
	 */
	private function ensureDependencies(): bool
	{
		if( !$this->userRepository ) {
			$this->output->error( "User repository not available." );
			$this->output->writeln( "This command requires a configured user repository." );
			return false;
		}

		// Create default input reader if not injected
		if( !$this->inputReader ) {
			$this->inputReader = new StdinInputReader( $this->output );
		}

		return true;
	}

	/**
	 * Find user by ID or username.
	 *
	 * @param string $identifier User ID (numeric) or username
	 * @return User|null Found user or null
	 */
	private function findUser( string $identifier ): ?User
	{
		if( is_numeric( $identifier ) ) {
			return $this->userRepository->findById( (int)$identifier );
		}

		return $this->userRepository->findByUsername( $identifier );
	}

	/**
	 * Display user information before deletion.
	 *
	 * @param User $user
	 * @return void
	 */
	private function displayUserInfo( User $user ): void
	{
		$this->output->warning( "You are about to delete the following user:" );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
		$this->output->writeln( "  ID:       " . $user->getId() );
		$this->output->writeln( "  Username: " . $user->getUsername() );
		$this->output->writeln( "  Email:    " . $user->getEmail() );
		$this->output->writeln( "  Role:     " . $user->getRole() );
		$this->output->writeln( "  Status:   " . $user->getStatus() );
		$this->output->writeln( "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" );
		$this->output->newLine();
	}

	/**
	 * Prompt user to confirm deletion.
	 *
	 * @return bool True if confirmed, false otherwise
	 */
	private function confirmDeletion(): bool
	{
		$response = $this->inputReader->prompt(
			"Are you sure you want to delete this user? Type 'DELETE' to confirm: "
		);

		return trim( $response ) === 'DELETE';
	}

	/**
	 * Delete the user.
	 *
	 * @param User $user
	 * @return int Exit code
	 */
	private function deleteUser( User $user ): int
	{
		try {
			$deleted = $this->userRepository->delete( $user->getId() );

			if( $deleted ) {
				$this->output->success( "User deleted successfully." );
				return 0;
			}

			$this->output->error( "Failed to delete user." );
			return 1;
		} catch( \Exception $e ) {
			$this->output->error( "Error: " . $e->getMessage() );
			return 1;
		}
	}

	/**
	 * Show usage information.
	 *
	 * @return void
	 */
	private function showUsage(): void
	{
		$this->output->writeln( "Usage:" );
		$this->output->writeln( "  php neuron cms:user:delete <id or username>" );
		$this->output->writeln( "  php neuron cms:user:delete <id or username> --force" );
		$this->output->newLine();
		$this->output->writeln( "Options:" );
		$this->output->writeln( "  -f, --force    Skip confirmation prompt" );
	}
}
