<?php

namespace Tests\Unit\Cms\Cli\Commands\User;

use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\User\DeleteCommand;
use Neuron\Cms\Cli\IO\TestInputReader;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Improved test for DeleteCommand showing benefits of refactored architecture.
 *
 * Key improvements:
 * - No Registry setup required
 * - Easy to inject mocks
 * - Can test interactive prompts
 * - Clear test setup
 * - No global state
 */
class DeleteCommandTest extends TestCase
{
	private IUserRepository $mockRepository;
	private TestInputReader $testInputReader;
	private Output $mockOutput;
	private DeleteCommand $command;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mocks
		$this->mockRepository = $this->createMock( IUserRepository::class );
		$this->testInputReader = new TestInputReader();
		$this->mockOutput = $this->createMock( Output::class );

		// Create command with injected dependencies
		$this->command = new DeleteCommand(
			$this->mockRepository,
			$this->testInputReader
		);

		$this->command->setOutput( $this->mockOutput );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:user:delete', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals(
			'Delete a user by ID or username',
			$this->command->getDescription()
		);
	}

	public function testExecuteWithoutIdentifierShowsError(): void
	{
		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Please provide a user ID or username.' );

		$result = $this->command->execute( [] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteWithNonExistentUserShowsError(): void
	{
		$this->mockRepository
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( "User '999' not found." );

		$result = $this->command->execute( [ 'identifier' => '999' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteDeletesUserWithNumericId(): void
	{
		// Setup user
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		// Mock repository to find and delete user
		$this->mockRepository
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->mockRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( true );

		// Setup test input to confirm deletion
		$this->testInputReader->addResponse( 'DELETE' );

		// Expect success message
		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' )
			->with( 'User deleted successfully.' );

		// Execute command
		$result = $this->command->execute( [ 'identifier' => '1' ] );

		// Assert success
		$this->assertEquals( 0, $result );
	}

	public function testExecuteDeletesUserByUsername(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findByUsername' )
			->with( 'testuser' )
			->willReturn( $user );

		$this->mockRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( true );

		$this->testInputReader->addResponse( 'DELETE' );

		$result = $this->command->execute( [ 'identifier' => 'testuser' ] );

		$this->assertEquals( 0, $result );
	}

	public function testExecuteCancelsWhenUserDoesNotConfirm(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		// Repository delete should NOT be called
		$this->mockRepository
			->expects( $this->never() )
			->method( 'delete' );

		// User types incorrect confirmation
		$this->testInputReader->addResponse( 'CANCEL' );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Deletion cancelled.' );

		$result = $this->command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteWithForceOptionSkipsConfirmation(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		$this->mockRepository
			->expects( $this->once() )
			->method( 'delete' )
			->willReturn( true );

		// No input reader response needed - force flag skips confirmation

		$result = $this->command->execute( [
			'identifier' => '1',
			'force' => true
		] );

		$this->assertEquals( 0, $result );

		// Verify no prompts were shown
		$this->assertEmpty( $this->testInputReader->getPromptHistory() );
	}

	public function testExecuteDisplaysUserInformation(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );
		$user->method( 'getRole' )->willReturn( 'admin' );
		$user->method( 'getStatus' )->willReturn( 'active' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		$this->mockRepository
			->method( 'delete' )
			->willReturn( true );

		$this->testInputReader->addResponse( 'DELETE' );

		// Verify user info is displayed
		$this->mockOutput
			->expects( $this->once() )
			->method( 'warning' )
			->with( 'You are about to delete the following user:' );

		$this->mockOutput
			->expects( $this->atLeast( 5 ) ) // At least 5 writeln calls for user info
			->method( 'writeln' );

		$this->command->execute( [ 'identifier' => '1' ] );
	}

	public function testExecuteHandlesRepositoryException(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		$this->mockRepository
			->method( 'delete' )
			->willThrowException( new \Exception( 'Database error' ) );

		$this->testInputReader->addResponse( 'DELETE' );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Error: Database error' );

		$result = $this->command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteHandlesDeleteFailure(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		// Delete returns false (failure but no exception)
		$this->mockRepository
			->method( 'delete' )
			->willReturn( false );

		$this->testInputReader->addResponse( 'DELETE' );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Failed to delete user.' );

		$result = $this->command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testConfirmationPromptIsCorrect(): void
	{
		$user = $this->createUser( 1, 'testuser', 'test@example.com' );

		$this->mockRepository
			->method( 'findById' )
			->willReturn( $user );

		$this->mockRepository
			->method( 'delete' )
			->willReturn( true );

		$this->testInputReader->addResponse( 'DELETE' );

		$this->command->execute( [ 'identifier' => '1' ] );

		// Verify the exact prompt that was shown
		$prompts = $this->testInputReader->getPromptHistory();
		$this->assertCount( 1, $prompts );
		$this->assertEquals(
			"Are you sure you want to delete this user? Type 'DELETE' to confirm: ",
			$prompts[0]
		);
	}

	/**
	 * Helper to create a mock user.
	 */
	private function createUser( int $id, string $username, string $email ): User
	{
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getUsername' )->willReturn( $username );
		$user->method( 'getEmail' )->willReturn( $email );
		$user->method( 'getRole' )->willReturn( 'member' );
		$user->method( 'getStatus' )->willReturn( 'active' );

		return $user;
	}
}
