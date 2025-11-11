<?php

namespace Neuron\Cms\Tests\Repositories;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Models\User;
use Neuron\Data\Setting\SettingManager;
use PDO;
use DateTimeImmutable;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseUserRepositoryTest extends TestCase
{
	private DatabaseUserRepository $repository;
	private PDO $pdo;
	private string $dbPath;

	protected function setUp(): void
	{
		// Create in-memory SQLite database for testing
		$this->dbPath = ':memory:';

		$config = [
			'adapter' => 'sqlite',
			'name' => $this->dbPath
		];

		// Mock SettingManager
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		$this->repository = new DatabaseUserRepository( $settings );

		// Get PDO connection via reflection to create table
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setAccessible( true );
		$this->pdo = $property->getValue( $this->repository );

		// Create users table
		$this->createUsersTable();
	}

	private function createUsersTable(): void
	{
		$sql = "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				role VARCHAR(50) DEFAULT 'subscriber',
				status VARCHAR(50) DEFAULT 'active',
				email_verified BOOLEAN DEFAULT 0,
				two_factor_secret VARCHAR(255) NULL,
				remember_token VARCHAR(255) NULL,
				failed_login_attempts INTEGER DEFAULT 0,
				locked_until TIMESTAMP NULL,
				last_login_at TIMESTAMP NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		";

		$this->pdo->exec( $sql );
	}

	public function testConstructorWithSqlite(): void
	{
		$config = [
			'adapter' => 'sqlite',
			'name' => ':memory:'
		];

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		$repository = new DatabaseUserRepository( $settings );
		$this->assertInstanceOf( DatabaseUserRepository::class, $repository );
	}

	public function testConstructorWithMysql(): void
	{
		$this->expectException( \PDOException::class );

		$config = [
			'adapter' => 'mysql',
			'host' => 'localhost',
			'port' => 3306,
			'name' => 'test_db',
			'user' => 'invalid_user',
			'pass' => 'invalid_pass',
			'charset' => 'utf8mb4'
		];

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		new DatabaseUserRepository( $settings );
	}

	public function testConstructorWithInvalidAdapter(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unsupported database adapter: invalid' );

		$config = [
			'adapter' => 'invalid',
			'name' => 'test.db'
		];

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		new DatabaseUserRepository( $settings );
	}

	public function testFindById(): void
	{
		$user = $this->createTestUser();
		$createdUser = $this->repository->create( $user );

		$foundUser = $this->repository->findById( $createdUser->getId() );

		$this->assertNotNull( $foundUser );
		$this->assertEquals( $createdUser->getId(), $foundUser->getId() );
		$this->assertEquals( 'testuser', $foundUser->getUsername() );
	}

	public function testFindByIdNotFound(): void
	{
		$foundUser = $this->repository->findById( 999 );
		$this->assertNull( $foundUser );
	}

	public function testFindByUsername(): void
	{
		$user = $this->createTestUser();
		$this->repository->create( $user );

		$foundUser = $this->repository->findByUsername( 'testuser' );

		$this->assertNotNull( $foundUser );
		$this->assertEquals( 'testuser', $foundUser->getUsername() );
	}

	public function testFindByUsernameNotFound(): void
	{
		$foundUser = $this->repository->findByUsername( 'nonexistent' );
		$this->assertNull( $foundUser );
	}

	public function testFindByEmail(): void
	{
		$user = $this->createTestUser();
		$this->repository->create( $user );

		$foundUser = $this->repository->findByEmail( 'test@example.com' );

		$this->assertNotNull( $foundUser );
		$this->assertEquals( 'test@example.com', $foundUser->getEmail() );
	}

	public function testFindByEmailNotFound(): void
	{
		$foundUser = $this->repository->findByEmail( 'nonexistent@example.com' );
		$this->assertNull( $foundUser );
	}

	public function testFindByRememberToken(): void
	{
		$user = $this->createTestUser();
		$token = hash( 'sha256', 'test_token_12345' );
		$user->setRememberToken( $token );

		$this->repository->create( $user );

		$foundUser = $this->repository->findByRememberToken( $token );

		$this->assertNotNull( $foundUser );
		$this->assertEquals( $token, $foundUser->getRememberToken() );
	}

	public function testFindByRememberTokenNotFound(): void
	{
		$foundUser = $this->repository->findByRememberToken( 'nonexistent_token' );
		$this->assertNull( $foundUser );
	}

	public function testCreate(): void
	{
		$user = $this->createTestUser();

		$createdUser = $this->repository->create( $user );

		$this->assertNotNull( $createdUser->getId() );
		$this->assertGreaterThan( 0, $createdUser->getId() );
		$this->assertEquals( 'testuser', $createdUser->getUsername() );
		$this->assertEquals( 'test@example.com', $createdUser->getEmail() );
	}

	public function testCreateWithDuplicateUsername(): void
	{
		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$user2 = new User();
		$user2->setUsername( 'testuser' );
		$user2->setEmail( 'different@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username already exists' );

		$this->repository->create( $user2 );
	}

	public function testCreateWithDuplicateEmail(): void
	{
		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$user2 = new User();
		$user2->setUsername( 'different_user' );
		$user2->setEmail( 'test@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Email already exists' );

		$this->repository->create( $user2 );
	}

	public function testUpdate(): void
	{
		$user = $this->createTestUser();
		$createdUser = $this->repository->create( $user );

		$createdUser->setEmail( 'updated@example.com' );
		$createdUser->setRole( User::ROLE_ADMIN );

		$result = $this->repository->update( $createdUser );

		$this->assertTrue( $result );

		$updatedUser = $this->repository->findById( $createdUser->getId() );
		$this->assertEquals( 'updated@example.com', $updatedUser->getEmail() );
		$this->assertEquals( User::ROLE_ADMIN, $updatedUser->getRole() );
	}

	public function testUpdateWithoutId(): void
	{
		$user = new User();
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );

		$result = $this->repository->update( $user );
		$this->assertFalse( $result );
	}

	public function testUpdateWithDuplicateUsername(): void
	{
		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$user2 = new User();
		$user2->setUsername( 'user2' );
		$user2->setEmail( 'user2@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );
		$createdUser2 = $this->repository->create( $user2 );

		$createdUser2->setUsername( 'testuser' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Username already exists' );

		$this->repository->update( $createdUser2 );
	}

	public function testUpdateWithDuplicateEmail(): void
	{
		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$user2 = new User();
		$user2->setUsername( 'user2' );
		$user2->setEmail( 'user2@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );
		$createdUser2 = $this->repository->create( $user2 );

		$createdUser2->setEmail( 'test@example.com' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Email already exists' );

		$this->repository->update( $createdUser2 );
	}

	public function testDelete(): void
	{
		$user = $this->createTestUser();
		$createdUser = $this->repository->create( $user );

		$result = $this->repository->delete( $createdUser->getId() );

		$this->assertTrue( $result );

		$foundUser = $this->repository->findById( $createdUser->getId() );
		$this->assertNull( $foundUser );
	}

	public function testDeleteNonExistent(): void
	{
		$result = $this->repository->delete( 999 );
		$this->assertFalse( $result );
	}

	public function testAll(): void
	{
		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$user2 = new User();
		$user2->setUsername( 'user2' );
		$user2->setEmail( 'user2@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );
		$this->repository->create( $user2 );

		$users = $this->repository->all();

		$this->assertCount( 2, $users );
		$this->assertContainsOnlyInstancesOf( User::class, $users );
	}

	public function testAllEmpty(): void
	{
		$users = $this->repository->all();
		$this->assertEmpty( $users );
	}

	public function testCount(): void
	{
		$this->assertEquals( 0, $this->repository->count() );

		$user1 = $this->createTestUser();
		$this->repository->create( $user1 );

		$this->assertEquals( 1, $this->repository->count() );

		$user2 = new User();
		$user2->setUsername( 'user2' );
		$user2->setEmail( 'user2@example.com' );
		$user2->setPasswordHash( 'hashed_password' );
		$user2->setRole( User::ROLE_SUBSCRIBER );
		$this->repository->create( $user2 );

		$this->assertEquals( 2, $this->repository->count() );
	}

	public function testMapRowToUserWithAllFields(): void
	{
		$user = $this->createTestUser();
		$user->setTwoFactorSecret( 'secret_123' );
		$user->setRememberToken( 'token_123' );
		$user->setFailedLoginAttempts( 3 );
		$user->setLockedUntil( new DateTimeImmutable( '+15 minutes' ) );
		$user->setLastLoginAt( new DateTimeImmutable( '-1 hour' ) );

		$createdUser = $this->repository->create( $user );

		$foundUser = $this->repository->findById( $createdUser->getId() );

		$this->assertNotNull( $foundUser );
		$this->assertEquals( 'secret_123', $foundUser->getTwoFactorSecret() );
		$this->assertEquals( 'token_123', $foundUser->getRememberToken() );
		$this->assertEquals( 3, $foundUser->getFailedLoginAttempts() );
		$this->assertNotNull( $foundUser->getLockedUntil() );
		$this->assertNotNull( $foundUser->getLastLoginAt() );
	}

	private function createTestUser(): User
	{
		$user = new User();
		$user->setUsername( 'testuser' );
		$user->setEmail( 'test@example.com' );
		$user->setPasswordHash( 'hashed_password_12345' );
		$user->setRole( User::ROLE_SUBSCRIBER );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user->setCreatedAt( new DateTimeImmutable() );

		return $user;
	}
}
