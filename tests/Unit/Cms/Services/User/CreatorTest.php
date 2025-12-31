<?php

namespace Tests\Cms\Services\User;

use DateTimeImmutable;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\Creator;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
	private Creator $_creator;
	private IUserRepository $_mockUserRepository;
	private PasswordHasher $_mockPasswordHasher;

	protected function setUp(): void
	{
		$this->_mockUserRepository = $this->createMock( IUserRepository::class );
		$this->_mockPasswordHasher = $this->createMock( PasswordHasher::class );

		$this->_creator = new Creator(
			$this->_mockUserRepository,
			$this->_mockPasswordHasher
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto( string $username, string $email, string $password, string $role, ?string $timezone = null ): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/users/create-user-request.yaml' );
		$dto = $factory->create();

		$dto->username = $username;
		$dto->email = $email;
		$dto->password = $password;
		$dto->role = $role;
		if( $timezone !== null )
		{
			$dto->timezone = $timezone;
		}

		return $dto;
	}

	public function testCreatesUserWithRequiredFields(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->with( 'ValidPassword123!' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->with( 'ValidPassword123!' )
			->willReturn( 'hashed_password' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( User $user ) {
				return $user->getUsername() === 'testuser'
					&& $user->getEmail() === 'test@example.com'
					&& $user->getPasswordHash() === 'hashed_password'
					&& $user->getRole() === User::ROLE_SUBSCRIBER
					&& $user->getStatus() === User::STATUS_ACTIVE
					&& $user->isEmailVerified() === true
					&& $user->getCreatedAt() instanceof DateTimeImmutable;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'testuser',
			'test@example.com',
			'ValidPassword123!',
			User::ROLE_SUBSCRIBER
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'testuser', $result->getUsername() );
		$this->assertEquals( 'test@example.com', $result->getEmail() );
	}

	public function testThrowsExceptionForInvalidPassword(): void
	{
		// Use a password that passes DTO validation (length) but fails password hasher validation (complexity)
		$weakPassword = 'weakpass';  // 8 chars, passes DTO min length but no uppercase/special chars

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
			'testuser',
			'test@example.com',
			$weakPassword,
			User::ROLE_SUBSCRIBER
		);

		$this->_creator->create( $dto );
	}

	public function testCreatesAdminUser(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->willReturn( 'hashed_password' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( User $user ) {
				return $user->getRole() === User::ROLE_ADMIN;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'admin',
			'admin@example.com',
			'AdminPassword123!',
			User::ROLE_ADMIN
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( User::ROLE_ADMIN, $result->getRole() );
	}

	public function testCreatesEditorUser(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->willReturn( 'hashed_password' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( User $user ) {
				return $user->getRole() === User::ROLE_EDITOR;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'editor',
			'editor@example.com',
			'EditorPassword123!',
			User::ROLE_EDITOR
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( User::ROLE_EDITOR, $result->getRole() );
	}

	public function testSetsActiveStatus(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->willReturn( 'hashed_password' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( User $user ) {
				return $user->getStatus() === User::STATUS_ACTIVE;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'testuser',
			'test@example.com',
			'Password123!',
			User::ROLE_SUBSCRIBER
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( User::STATUS_ACTIVE, $result->getStatus() );
	}

	public function testSetsEmailVerified(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->willReturn( true );

		$this->_mockPasswordHasher
			->method( 'hash' )
			->willReturn( 'hashed_password' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( User $user ) {
				return $user->isEmailVerified() === true;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'testuser',
			'test@example.com',
			'Password123!',
			User::ROLE_SUBSCRIBER
		);

		$result = $this->_creator->create( $dto );

		$this->assertTrue( $result->isEmailVerified() );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );
		$passwordHasher = $this->createMock( PasswordHasher::class );

		$creator = new Creator( $userRepository, $passwordHasher );

		$this->assertInstanceOf( Creator::class, $creator );
	}

	public function testConstructorWithEventEmitter(): void
	{
		$userRepository = $this->createMock( IUserRepository::class );
		$passwordHasher = $this->createMock( PasswordHasher::class );
		$eventEmitter = $this->createMock( \Neuron\Events\Emitter::class );

		$passwordHasher
			->method( 'meetsRequirements' )
			->willReturn( true );

		$passwordHasher
			->method( 'hash' )
			->willReturn( 'hashed_password' );

		$userRepository
			->method( 'create' )
			->willReturnArgument( 0 );

		// Event emitter should emit UserCreatedEvent
		$eventEmitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\UserCreatedEvent::class ) );

		$creator = new Creator( $userRepository, $passwordHasher, $eventEmitter );

		$dto = $this->createDto(
			'testuser',
			'test@example.com',
			'Password123!',
			User::ROLE_SUBSCRIBER
		);

		$creator->create( $dto );
	}
}
