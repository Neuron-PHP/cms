<?php

namespace Neuron\Cms\Tests\Repositories;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Repositories\DatabaseEmailVerificationTokenRepository;
use Neuron\Cms\Models\EmailVerificationToken;
use Neuron\Data\Setting\SettingManager;
use PDO;
use DateTimeImmutable;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseEmailVerificationTokenRepositoryTest extends TestCase
{
	private DatabaseEmailVerificationTokenRepository $repository;
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

		$this->repository = new DatabaseEmailVerificationTokenRepository( $settings );

		// Get PDO connection via reflection to create table
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setAccessible( true );
		$this->pdo = $property->getValue( $this->repository );

		// Create tables
		$this->createTables();
	}

	private function createTables(): void
	{
		// Create users table (needed for foreign key)
		$sql = "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL
			)
		";
		$this->pdo->exec( $sql );

		// Create email_verification_tokens table
		$sql = "
			CREATE TABLE email_verification_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL,
				token VARCHAR(64) NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				expires_at TIMESTAMP NOT NULL,
				FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
			)
		";
		$this->pdo->exec( $sql );

		// Insert a test user
		$stmt = $this->pdo->prepare( "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)" );
		$stmt->execute( ['testuser', 'test@example.com', 'hash123'] );
	}

	public function testConstructor(): void
	{
		$this->assertInstanceOf( DatabaseEmailVerificationTokenRepository::class, $this->repository );
	}

	public function testCreateToken(): void
	{
		$userId = 1;
		$tokenHash = hash( 'sha256', 'test-token' );
		$token = new EmailVerificationToken( $userId, $tokenHash, 60 );

		$createdToken = $this->repository->create( $token );

		$this->assertInstanceOf( EmailVerificationToken::class, $createdToken );
		$this->assertNotNull( $createdToken->getId() );
		$this->assertEquals( $userId, $createdToken->getUserId() );
		$this->assertEquals( $tokenHash, $createdToken->getToken() );
	}

	public function testFindByToken(): void
	{
		// Create a token
		$userId = 1;
		$tokenHash = hash( 'sha256', 'find-test-token' );
		$token = new EmailVerificationToken( $userId, $tokenHash, 60 );
		$this->repository->create( $token );

		// Find it
		$foundToken = $this->repository->findByToken( $tokenHash );

		$this->assertInstanceOf( EmailVerificationToken::class, $foundToken );
		$this->assertEquals( $userId, $foundToken->getUserId() );
		$this->assertEquals( $tokenHash, $foundToken->getToken() );
	}

	public function testFindByTokenNotFound(): void
	{
		$foundToken = $this->repository->findByToken( 'nonexistent-token-hash' );

		$this->assertNull( $foundToken );
	}

	public function testFindByUserId(): void
	{
		// Create multiple tokens for same user using manual insert to control timestamps
		$userId = 1;

		// Insert first token (older)
		$stmt = $this->pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at)
			 VALUES (?, ?, ?, ?)"
		);
		$stmt->execute([
			$userId,
			hash( 'sha256', 'token1' ),
			'2024-01-01 12:00:00',
			'2024-01-01 13:00:00'
		]);

		// Insert second token (newer)
		$stmt->execute([
			$userId,
			hash( 'sha256', 'token2' ),
			'2024-01-01 12:05:00',
			'2024-01-01 13:05:00'
		]);

		// Find should return most recent
		$foundToken = $this->repository->findByUserId( $userId );

		$this->assertInstanceOf( EmailVerificationToken::class, $foundToken );
		$this->assertEquals( $userId, $foundToken->getUserId() );
		$this->assertEquals( hash( 'sha256', 'token2' ), $foundToken->getToken() );
	}

	public function testFindByUserIdNotFound(): void
	{
		$foundToken = $this->repository->findByUserId( 999 );

		$this->assertNull( $foundToken );
	}

	public function testDeleteByUserId(): void
	{
		// Create tokens for user
		$userId = 1;
		$token1 = new EmailVerificationToken( $userId, hash( 'sha256', 'delete-token1' ), 60 );
		$token2 = new EmailVerificationToken( $userId, hash( 'sha256', 'delete-token2' ), 60 );

		$this->repository->create( $token1 );
		$this->repository->create( $token2 );

		// Delete all tokens for user
		$deleteCount = $this->repository->deleteByUserId( $userId );

		$this->assertEquals( 2, $deleteCount );

		// Verify they're gone
		$foundToken = $this->repository->findByUserId( $userId );
		$this->assertNull( $foundToken );
	}

	public function testDeleteByToken(): void
	{
		// Create a token
		$userId = 1;
		$tokenHash = hash( 'sha256', 'delete-by-token' );
		$token = new EmailVerificationToken( $userId, $tokenHash, 60 );
		$this->repository->create( $token );

		// Delete it
		$result = $this->repository->deleteByToken( $tokenHash );

		$this->assertTrue( $result );

		// Verify it's gone
		$foundToken = $this->repository->findByToken( $tokenHash );
		$this->assertNull( $foundToken );
	}

	public function testDeleteByTokenNotFound(): void
	{
		$result = $this->repository->deleteByToken( 'nonexistent-token' );

		$this->assertFalse( $result );
	}

	public function testDeleteExpired(): void
	{
		// Create an expired token
		$userId = 1;
		$expiredToken = new EmailVerificationToken();
		$expiredToken->setUserId( $userId );
		$expiredToken->setToken( hash( 'sha256', 'expired-token' ) );
		$expiredToken->setCreatedAt( new DateTimeImmutable( '2020-01-01 12:00:00' ) );
		$expiredToken->setExpiresAt( new DateTimeImmutable( '2020-01-01 13:00:00' ) );

		// Manually insert expired token
		$stmt = $this->pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at)
			 VALUES (?, ?, ?, ?)"
		);
		$stmt->execute([
			$expiredToken->getUserId(),
			$expiredToken->getToken(),
			$expiredToken->getCreatedAt()->format( 'Y-m-d H:i:s' ),
			$expiredToken->getExpiresAt()->format( 'Y-m-d H:i:s' )
		]);

		// Create a valid token
		$validToken = new EmailVerificationToken( $userId, hash( 'sha256', 'valid-token' ), 60 );
		$this->repository->create( $validToken );

		// Delete expired tokens
		$deleteCount = $this->repository->deleteExpired();

		$this->assertEquals( 1, $deleteCount );

		// Verify expired token is gone
		$foundExpired = $this->repository->findByToken( $expiredToken->getToken() );
		$this->assertNull( $foundExpired );

		// Verify valid token still exists
		$foundValid = $this->repository->findByToken( $validToken->getToken() );
		$this->assertNotNull( $foundValid );
	}
}
