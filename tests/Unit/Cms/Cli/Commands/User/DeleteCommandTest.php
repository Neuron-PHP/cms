<?php

namespace Tests\Unit\Cms\Cli\Commands\User;

use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\User\DeleteCommand;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class DeleteCommandTest extends TestCase
{
	private DeleteCommand $command;
	private Output $mockOutput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new DeleteCommand();
		$this->mockOutput = $this->createMock( Output::class );

		// Use setOutput method
		$this->command->setOutput( $this->mockOutput );

		// Setup mock settings in Registry
		$mockSettings = $this->createMock( SettingManager::class );
		$mockSettings->method( 'get' )->willReturnCallback( function( $section, $key = null ) {
			if( $section === 'database' && $key === 'driver' ) return 'sqlite';
			if( $section === 'database' && $key === 'name' ) return ':memory:';
			return null;
		});
		Registry::getInstance()->set( 'Settings', $mockSettings );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cms:user:delete', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'Delete a user', $this->command->getDescription() );
	}

	public function testExecuteWithoutIdentifier(): void
	{
		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Please provide a user ID or username.' );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'writeln' )
			->with( 'Usage: php neuron cms:user:delete <id or username>' );

		$result = $this->command->execute( [] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteWithoutSettingsInRegistry(): void
	{
		// Clear settings from Registry
		Registry::getInstance()->reset();

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Application not initialized: Settings not found in Registry' );

		$result = $this->command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteUserNotFoundById(): void
	{
		// Mock repository returning null (user not found)
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findById' )->with( 123 )->willReturn( null );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( "User '123' not found." );

		$result = $command->execute( [ 'identifier' => '123' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteUserNotFoundByUsername(): void
	{
		// Mock repository returning null (user not found)
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findByUsername' )->with( 'nonexistent' )->willReturn( null );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( "User 'nonexistent' not found." );

		$result = $command->execute( [ 'identifier' => 'nonexistent' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteDeletionCancelled(): void
	{
		// Create mock user
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getId' )->willReturn( 1 );
		$mockUser->method( 'getUsername' )->willReturn( 'testuser' );
		$mockUser->method( 'getEmail' )->willReturn( 'test@example.com' );
		$mockUser->method( 'getRole' )->willReturn( 'member' );
		$mockUser->method( 'getStatus' )->willReturn( 'active' );

		// Mock repository
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findById' )->with( 1 )->willReturn( $mockUser );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository', 'prompt'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->method( 'prompt' )->willReturn( 'no' ); // User cancels
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Deletion cancelled.' );

		$result = $command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteDeletionSuccessful(): void
	{
		// Create mock user
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getId' )->willReturn( 1 );
		$mockUser->method( 'getUsername' )->willReturn( 'testuser' );
		$mockUser->method( 'getEmail' )->willReturn( 'test@example.com' );
		$mockUser->method( 'getRole' )->willReturn( 'member' );
		$mockUser->method( 'getStatus' )->willReturn( 'active' );

		// Mock repository
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findById' )->with( 1 )->willReturn( $mockUser );
		$mockRepo->method( 'delete' )->with( 1 )->willReturn( true );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository', 'prompt'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->method( 'prompt' )->willReturn( 'DELETE' ); // User confirms
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'success' )
			->with( 'User deleted successfully.' );

		$result = $command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 0, $result );
	}

	public function testExecuteDeletionFailed(): void
	{
		// Create mock user
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getId' )->willReturn( 1 );
		$mockUser->method( 'getUsername' )->willReturn( 'testuser' );
		$mockUser->method( 'getEmail' )->willReturn( 'test@example.com' );
		$mockUser->method( 'getRole' )->willReturn( 'member' );
		$mockUser->method( 'getStatus' )->willReturn( 'active' );

		// Mock repository
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findById' )->with( 1 )->willReturn( $mockUser );
		$mockRepo->method( 'delete' )->with( 1 )->willReturn( false );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository', 'prompt'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->method( 'prompt' )->willReturn( 'DELETE' );
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Failed to delete user.' );

		$result = $command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}

	public function testExecuteDeletionException(): void
	{
		// Create mock user
		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getId' )->willReturn( 1 );
		$mockUser->method( 'getUsername' )->willReturn( 'testuser' );
		$mockUser->method( 'getEmail' )->willReturn( 'test@example.com' );
		$mockUser->method( 'getRole' )->willReturn( 'member' );
		$mockUser->method( 'getStatus' )->willReturn( 'active' );

		// Mock repository that throws exception
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'findById' )->with( 1 )->willReturn( $mockUser );
		$mockRepo->method( 'delete' )->will( $this->throwException( new \Exception( 'Database error' ) ) );

		$command = $this->getMockBuilder( DeleteCommand::class )
			->onlyMethods( ['getUserRepository', 'prompt'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->method( 'prompt' )->willReturn( 'DELETE' );
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Error: Database error' );

		$result = $command->execute( [ 'identifier' => '1' ] );

		$this->assertEquals( 1, $result );
	}
}
