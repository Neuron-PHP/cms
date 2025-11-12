<?php

namespace Tests\Cms\Services\User;

use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\Updater;
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

	public function testUpdatesUserWithoutPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'oldusername' );
		$user->setEmail( 'old@example.com' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'existing_hash' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getUsername() === 'newusername'
					&& $u->getEmail() === 'new@example.com'
					&& $u->getRole() === User::ROLE_EDITOR
					&& $u->getPasswordHash() === 'existing_hash';
			} ) );

		$result = $this->_updater->update(
			$user,
			'newusername',
			'new@example.com',
			User::ROLE_EDITOR,
			null
		);

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

		$result = $this->_updater->update(
			$user,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			'NewPassword123!'
		);

		$this->assertEquals( 'new_hash', $result->getPasswordHash() );
	}

	public function testThrowsExceptionForInvalidPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setPasswordHash( 'old_hash' );

		$this->_mockPasswordHasher
			->method( 'meetsRequirements' )
			->with( 'weak' )
			->willReturn( false );

		$this->_mockPasswordHasher
			->method( 'getValidationErrors' )
			->with( 'weak' )
			->willReturn( [ 'Password too short' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/^Password does not meet requirements/' );

		$this->_updater->update(
			$user,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			'weak'
		);
	}

	public function testIgnoresEmptyPassword(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setPasswordHash( 'existing_hash' );

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

		$result = $this->_updater->update(
			$user,
			'testuser',
			'test@example.com',
			User::ROLE_SUBSCRIBER,
			''
		);

		$this->assertEquals( 'existing_hash', $result->getPasswordHash() );
	}

	public function testUpdatesRole(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setPasswordHash( 'hash' );

		$this->_mockUserRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( User $u ) {
				return $u->getRole() === User::ROLE_ADMIN;
			} ) );

		$result = $this->_updater->update(
			$user,
			'testuser',
			'test@example.com',
			User::ROLE_ADMIN,
			null
		);

		$this->assertEquals( User::ROLE_ADMIN, $result->getRole() );
	}
}
