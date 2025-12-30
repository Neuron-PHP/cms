<?php

namespace Tests\Unit\Cms\Cli\Commands\User;

use Neuron\Cli\Console\Output;
use Neuron\Cms\Cli\Commands\User\ListCommand;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class ListCommandTest extends TestCase
{
	private ListCommand $command;
	private Output $mockOutput;

	protected function setUp(): void
	{
		parent::setUp();

		$this->command = new ListCommand();
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
		$this->assertEquals( 'cms:user:list', $this->command->getName() );
	}

	public function testGetDescription(): void
	{
		$this->assertEquals( 'List all users', $this->command->getDescription() );
	}

	public function testExecuteWithoutSettingsInRegistry(): void
	{
		// Clear settings from Registry
		Registry::getInstance()->reset();

		$this->mockOutput
			->expects( $this->once() )
			->method( 'error' )
			->with( 'Application not initialized: Settings not found in Registry' );

		$result = $this->command->execute();

		$this->assertEquals( 1, $result );
	}

	public function testExecuteWithNoUsers(): void
	{
		// Mock repository returning empty array
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'all' )->willReturn( [] );

		$command = $this->getMockBuilder( ListCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->setOutput( $this->mockOutput );

		$this->mockOutput
			->expects( $this->once() )
			->method( 'info' )
			->with( 'No users found.' );

		$result = $command->execute();

		$this->assertEquals( 0, $result );
	}

	public function testExecuteWithUsers(): void
	{
		// Create mock users
		$user1 = $this->createMock( User::class );
		$user1->method( 'getId' )->willReturn( 1 );
		$user1->method( 'getUsername' )->willReturn( 'admin' );
		$user1->method( 'getEmail' )->willReturn( 'admin@example.com' );
		$user1->method( 'getRole' )->willReturn( 'admin' );
		$user1->method( 'getStatus' )->willReturn( 'active' );
		$user1->method( 'isLockedOut' )->willReturn( false );
		$user1->method( 'getCreatedAt' )->willReturn( new \DateTimeImmutable( '2024-01-01 10:00:00' ) );

		$user2 = $this->createMock( User::class );
		$user2->method( 'getId' )->willReturn( 2 );
		$user2->method( 'getUsername' )->willReturn( 'editor' );
		$user2->method( 'getEmail' )->willReturn( 'editor@example.com' );
		$user2->method( 'getRole' )->willReturn( 'editor' );
		$user2->method( 'getStatus' )->willReturn( 'active' );
		$user2->method( 'isLockedOut' )->willReturn( false );
		$user2->method( 'getCreatedAt' )->willReturn( new \DateTimeImmutable( '2024-01-02 11:00:00' ) );

		// Mock repository returning users
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'all' )->willReturn( [$user1, $user2] );

		$command = $this->getMockBuilder( ListCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->setOutput( $this->mockOutput );

		// Expect writeln to be called multiple times for table display
		$this->mockOutput
			->expects( $this->atLeastOnce() )
			->method( 'writeln' );

		$result = $command->execute();

		$this->assertEquals( 0, $result );
	}

	public function testExecuteWithLockedOutUser(): void
	{
		// Create mock locked out user
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 3 );
		$user->method( 'getUsername' )->willReturn( 'locked' );
		$user->method( 'getEmail' )->willReturn( 'locked@example.com' );
		$user->method( 'getRole' )->willReturn( 'member' );
		$user->method( 'getStatus' )->willReturn( 'active' );
		$user->method( 'isLockedOut' )->willReturn( true );
		$user->method( 'getCreatedAt' )->willReturn( new \DateTimeImmutable( '2024-01-03 12:00:00' ) );

		// Mock repository
		$mockRepo = $this->createMock( DatabaseUserRepository::class );
		$mockRepo->method( 'all' )->willReturn( [$user] );

		$command = $this->getMockBuilder( ListCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( $mockRepo );
		$command->setOutput( $this->mockOutput );

		$result = $command->execute();

		$this->assertEquals( 0, $result );
	}

	public function testExecuteWithRepositoryFailure(): void
	{
		// Mock getUserRepository returning null (failure)
		$command = $this->getMockBuilder( ListCommand::class )
			->onlyMethods( ['getUserRepository'] )
			->getMock();
		$command->method( 'getUserRepository' )->willReturn( null );
		$command->setOutput( $this->mockOutput );

		$result = $command->execute();

		$this->assertEquals( 1, $result );
	}
}
