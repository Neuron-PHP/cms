<?php

namespace Tests\Cms\Services\User;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\Updater;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
	private Updater $_updater;
	private IUserRepository $_mockUserRepository;
	private PasswordHasher $_mockPasswordHasher;

	protected function setUp(): void
	{
		$this->_mockUserRepository = $this->createMock( IUserRepository::class );
		$this->_mockPasswordHasher = $this->createMock( PasswordHasher::class );

		$this->_updater = new Updater(
			$this->_mockUserRepository,
			$this->_mockPasswordHasher
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto( int $id, string $username, string $email, string $role, ?string $password = null ): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/users/update-user-request.yaml" );
		$dto = $factory->create();

		$dto->id = $id;
		$dto->username = $username;
		$dto->email = $email;
		$dto->role = $role;
		// Only set password if it's not null and not empty (password is optional in DTO)
		if( $password !== null && $password !== '' )
		{
			$dto->password = $password;
		}

		return $dto;
	}

	public function testUpdatesUserWithoutPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'oldusername' );
		$user->setEmail( 'old@example.com' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'existing_hash' );

		// Mock findById to return the user
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getUsername() === 'newusername'
					&& $u->getEmail() === 'new@example.com'
					&& $u->getRole() === User::ROLE_EDITOR
					&& $u->getPasswordHash() === 'existing_hash';
			} ) );

		$dto = $this->createDto(
			1,
			'newusername',
			'new@example.com',
			User::ROLE_EDITOR
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'newusername', $result->getUsername() );
		$this->assertEquals( 'new@example.com', $result->getEmail() );
		$this->assertEquals( User::ROLE_EDITOR, $result->getRole() );
		$this->assertEquals( 'existing_hash', $result->getPasswordHash() );
	}

	public function testUpdatesUserWithPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'old_hash' );

		// Mock findById to return the user
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->with( 'NewPassword123!' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->with( 'NewPassword123!' )
			->willReturn( 'new_hash' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getPasswordHash() === 'new_hash';
			} ) );

		$dto = $this->createDto(
			1,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			'NewPassword123!'
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'new_hash', $result->getPasswordHash() );
	}

	public function testThrowsExceptionForInvalidPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setPasswordHash( 'old_hash' );

		// Use a password that passes DTO validation (length) but fails password hasher validation (complexity)
		$weakPassword = 'weakpass';  // 8 chars, passes DTO min length but no uppercase/special chars

		// Mock findById to return the user
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->with( $weakPassword )
			->willReturn( false );

		$this->_mockPasswordHasher
			->method( 'getValidationErrors' )
			->with( $weakPassword )
			->willReturn( [ 'Missing uppercase letter', 'Missing special character' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/^Password does not meet requirements/' );

		$dto = $this->createDto(
			1,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			$weakPassword
		);

		$this->_updater->update( $dto );
	}

	public function testIgnoresEmptyPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setPasswordHash( 'existing_hash' );

		// Mock findById to return the user
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->_mockPasswordHasher
			->expects( $this->never() )
			->method( 'meetsRequirements' );

		$this->_mockPasswordHasher
			->expects( $this->never() )
			->method( 'hash' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getPasswordHash() === 'existing_hash';
			} ) );

		$dto = $this->createDto(
			1,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			''
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'existing_hash', $result->getPasswordHash() );
	}

	public function testUpdatesRole(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'hash' );

		// Mock findById to return the user
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $user );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getRole() === User::ROLE_ADMIN;
			} ) );

		$dto = $this->createDto(
			1,
			'testuser',
			'test@example.com',
			User::ROLE_ADMIN
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( User::ROLE_ADMIN, $result->getRole() );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );
		$passwordHasher = $this->createMock( PasswordHasher::class );

		$updater = new Updater( $userRepository, $passwordHasher );

		$this->assertInstanceOf( Updater::class, $updater );
	}

	public function testConstructorWithEventEmitter(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );
		$passwordHasher = $this->createMock( PasswordHasher::class );
		$eventEmitter = $this->createMock( \Neuron\Events\Emitter::class );

		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'existing_hash' );

		$userRepository
			->method( 'findById' )
			->willReturn( $user );

		$userRepository
			->method( 'update' );

		// Event emitter should emit UserUpdatedEvent
		$eventEmitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\UserUpdatedEvent::class ) );

		$updater = new Updater( $userRepository, $passwordHasher, $eventEmitter );

		$dto = $this->createDto(
			1,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER
		);

		$updater->update( $dto );
	}

	public function testThrowsExceptionWhenUserNotFound(): void
	{
		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'User with ID 999 not found' );

		$dto = $this->createDto(
			999,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER
		);

		$this->_updater->update( $dto );
	}
}
