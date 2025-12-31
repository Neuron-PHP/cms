<?php

namespace Tests\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\Deleter;
use PHPUnit\Framework\TestCase;

class DeleterTest extends TestCase
{
	private Deleter $_deleter;
	private IUserRepository $_mockUserRepository;

	protected function setUp(): void
	{
		$this->_mockUserRepository = $this->createMock( IUserRepository::class );

		$this->_deleter = new Deleter( $this->_mockUserRepository );
	}

	public function testDeletesUserById(): void
	{
		$user = new User();
		$user->setId( 5 );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 5 )
			->willReturn( $user );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 5 )
			->willReturn( true );

		$result = $this->_deleter->delete( 5 );

		$this->assertTrue( $result );
	}

	public function testDeletesMultipleUsers(): void
	{
		$user1 = new User();
		$user1->setId( 1 );
		$user2 = new User();
		$user2->setId( 2 );
		$user3 = new User();
		$user3->setId( 3 );

		$this->_mockUserRepository
			->expects( $this->exactly( 3 ) )
			->method( 'findById' )
			->withConsecutive( [ 1 ], [ 2 ], [ 3 ] )
			->willReturnOnConsecutiveCalls( $user1, $user2, $user3 );

		$this->_mockUserRepository
			->expects( $this->exactly( 3 ) )
			->method( 'delete' )
			->withConsecutive( [ 1 ], [ 2 ], [ 3 ] )
			->willReturn( true );

		$this->_deleter->delete( 1 );
		$this->_deleter->delete( 2 );
		$this->_deleter->delete( 3 );
	}

	public function testThrowsExceptionWhenUserNotFound(): void
	{
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 99 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'User not found' );

		$this->_deleter->delete( 99 );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );

		$deleter = new Deleter( $userRepository );

		$this->assertInstanceOf( Deleter::class, $deleter );
	}

	public function testConstructorWithEventEmitter(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );
		$eventEmitter = $this->createMock( \Neuron\Events\Emitter::class );

		$user = new User();
		$user->setId( 1 );

		$userRepository
			->method( 'findById' )
			->willReturn( $user );

		$userRepository
			->method( 'delete' )
			->willReturn( true );

		// Event emitter should emit UserDeletedEvent
		$eventEmitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\UserDeletedEvent::class ) );

		$deleter = new Deleter( $userRepository, $eventEmitter );

		$deleter->delete( 1 );
	}
}
