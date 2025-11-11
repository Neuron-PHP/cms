<?php

namespace Tests\Cms\Services\User;

use DateTimeImmutable;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\Creator;
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

		$result = $this->_creator->create(
			'testuser',
			'test@example.com',
			'ValidPassword123!',
			User::ROLE_SUBSCRIBER
		);

		$this->assertEquals( 'testuser', $result->getUsername() );
		$this->assertEquals( 'test@example.com', $result->getEmail() );
	}

	public function testThrowsExceptionForInvalidPassword(): void
	{
		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->with( 'weak' )
			->willReturn( false );

		$this->_mockPasswordHasher
			->method( 'getValidationErrors' )
			->with( 'weak' )
			->willReturn( [ 'Password too short', 'Missing uppercase letter' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Password does not meet requirements' );

		$this->_creator->create(
			'testuser',
			'test@example.com',
			'weak',
			User::ROLE_SUBSCRIBER
		);
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

		$result = $this->_creator->create(
			'admin',
			'admin@example.com',
			'AdminPassword123!',
			User::ROLE_ADMIN
		);

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

		$result = $this->_creator->create(
			'editor',
			'editor@example.com',
			'EditorPassword123!',
			User::ROLE_EDITOR
		);

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

		$result = $this->_creator->create(
			'testuser',
			'test@example.com',
			'Password123!',
			User::ROLE_SUBSCRIBER
		);

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

		$result = $this->_creator->create(
			'testuser',
			'test@example.com',
			'Password123!',
			User::ROLE_SUBSCRIBER
		);

		$this->assertTrue( $result->isEmailVerified() );
	}
}
