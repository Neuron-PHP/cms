<?php

namespace Tests\Unit\Cms\Repositories;

use DateTimeImmutable;
use Neuron\Cms\Models\PasswordResetToken;
use Neuron\Cms\Repositories\DatabasePasswordResetTokenRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabasePasswordResetTokenRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabasePasswordResetTokenRepository $repository;

	protected function setUp(): void
	{
		// Create in-memory SQLite database for testing
		$this->pdo = new PDO(
			'sqlite::memory:',
			null,
			null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);

		// Create password_reset_tokens table
		$this->createTable();

		// Create repository with injected PDO
		$settings = $this->createMock( SettingManager::class );
		$pdo = $this->pdo;

		$this->repository = new class( $settings, $pdo ) extends DatabasePasswordResetTokenRepository
		{
			public function __construct( SettingManager $settings, PDO $pdo )
			{
				// Skip parent constructor and inject PDO directly
				$reflection = new \ReflectionClass( DatabasePasswordResetTokenRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $pdo );
			}
		};
	}

	private function createTable(): void
	{
		$this->pdo->exec( "
			CREATE TABLE password_reset_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				email VARCHAR(255) NOT NULL,
				token VARCHAR(255) NOT NULL,
				created_at TIMESTAMP NOT NULL,
				expires_at TIMESTAMP NOT NULL
			)
		" );
	}

	private function createTestToken( array $overrides = [] ): PasswordResetToken
	{
		$data = array_merge([
			'email' => 'test@example.com',
			'token' => hash( 'sha256', 'test-token-' . uniqid() ),
			'created_at' => (new DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
			'expires_at' => (new DateTimeImmutable())->modify( '+1 hour' )->format( 'Y-m-d H:i:s' )
		], $overrides);

		return PasswordResetToken::fromArray( $data );
	}

	public function testCreateSavesToken(): void
	{
		$token = $this->createTestToken();

		$result = $this->repository->create( $token );

		$this->assertGreaterThan( 0, $result->getId() );
		$this->assertEquals( $token->getEmail(), $result->getEmail() );
		$this->assertEquals( $token->getToken(), $result->getToken() );
	}

	public function testCreateSetsId(): void
	{
		$token = $this->createTestToken();
		$this->assertNull( $token->getId() );

		$this->repository->create( $token );

		$this->assertGreaterThan( 0, $token->getId() );
	}

	public function testFindByTokenReturnsToken(): void
	{
		$token = $this->createTestToken([ 'token' => hash( 'sha256', 'findable-token' ) ]);
		$this->repository->create( $token );

		$found = $this->repository->findByToken( $token->getToken() );

		$this->assertNotNull( $found );
		$this->assertEquals( $token->getEmail(), $found->getEmail() );
		$this->assertEquals( $token->getToken(), $found->getToken() );
	}

	public function testFindByTokenReturnsNullForNonexistent(): void
	{
		$found = $this->repository->findByToken( hash( 'sha256', 'nonexistent' ) );

		$this->assertNull( $found );
	}

	public function testDeleteByEmailRemovesAllTokensForEmail(): void
	{
		// Create multiple tokens for same email
		$email = 'user@example.com';
		$this->repository->create( $this->createTestToken([
			'email' => $email,
			'token' => hash( 'sha256', 'token1' )
		]));

		$this->repository->create( $this->createTestToken([
			'email' => $email,
			'token' => hash( 'sha256', 'token2' )
		]));

		$this->repository->create( $this->createTestToken([
			'email' => 'other@example.com',
			'token' => hash( 'sha256', 'token3' )
		]));

		$deletedCount = $this->repository->deleteByEmail( $email );

		$this->assertEquals( 2, $deletedCount );

		// Verify the tokens are gone
		$this->assertNull( $this->repository->findByToken( hash( 'sha256', 'token1' ) ) );
		$this->assertNull( $this->repository->findByToken( hash( 'sha256', 'token2' ) ) );

		// Verify other email's token still exists
		$this->assertNotNull( $this->repository->findByToken( hash( 'sha256', 'token3' ) ) );
	}

	public function testDeleteByEmailReturnsZeroWhenNoTokensFound(): void
	{
		$deletedCount = $this->repository->deleteByEmail( 'nobody@example.com' );

		$this->assertEquals( 0, $deletedCount );
	}

	public function testDeleteByTokenRemovesSpecificToken(): void
	{
		$token = $this->createTestToken([ 'token' => hash( 'sha256', 'deletable' ) ]);
		$this->repository->create( $token );

		$result = $this->repository->deleteByToken( $token->getToken() );

		$this->assertTrue( $result );

		$found = $this->repository->findByToken( $token->getToken() );
		$this->assertNull( $found );
	}

	public function testDeleteByTokenReturnsFalseForNonexistent(): void
	{
		$result = $this->repository->deleteByToken( hash( 'sha256', 'nonexistent' ) );

		$this->assertFalse( $result );
	}

	public function testDeleteExpiredRemovesExpiredTokens(): void
	{
		// Create expired token (1 hour ago)
		$expiredToken = $this->createTestToken([
			'token' => hash( 'sha256', 'expired' ),
			'expires_at' => (new DateTimeImmutable())->modify( '-1 hour' )->format( 'Y-m-d H:i:s' )
		]);
		$this->repository->create( $expiredToken );

		// Create valid token (1 hour in future)
		$validToken = $this->createTestToken([
			'token' => hash( 'sha256', 'valid' ),
			'expires_at' => (new DateTimeImmutable())->modify( '+1 hour' )->format( 'Y-m-d H:i:s' )
		]);
		$this->repository->create( $validToken );

		$deletedCount = $this->repository->deleteExpired();

		$this->assertEquals( 1, $deletedCount );

		// Verify expired token is gone
		$this->assertNull( $this->repository->findByToken( $expiredToken->getToken() ) );

		// Verify valid token still exists
		$this->assertNotNull( $this->repository->findByToken( $validToken->getToken() ) );
	}

	public function testDeleteExpiredReturnsZeroWhenNoExpiredTokens(): void
	{
		// Create valid token
		$token = $this->createTestToken([
			'expires_at' => (new DateTimeImmutable())->modify( '+1 hour' )->format( 'Y-m-d H:i:s' )
		]);
		$this->repository->create( $token );

		$deletedCount = $this->repository->deleteExpired();

		$this->assertEquals( 0, $deletedCount );
	}

	public function testCreateMultipleTokensForSameEmail(): void
	{
		$email = 'user@example.com';

		$token1 = $this->createTestToken([
			'email' => $email,
			'token' => hash( 'sha256', 'token1' )
		]);
		$this->repository->create( $token1 );

		$token2 = $this->createTestToken([
			'email' => $email,
			'token' => hash( 'sha256', 'token2' )
		]);
		$this->repository->create( $token2 );

		$found1 = $this->repository->findByToken( $token1->getToken() );
		$found2 = $this->repository->findByToken( $token2->getToken() );

		$this->assertNotNull( $found1 );
		$this->assertNotNull( $found2 );
		$this->assertEquals( $email, $found1->getEmail() );
		$this->assertEquals( $email, $found2->getEmail() );
	}

	public function testTokenPreservesExpirationTime(): void
	{
		$expiresAt = (new DateTimeImmutable())->modify( '+2 hours' );
		$token = $this->createTestToken([ 'expires_at' => $expiresAt->format( 'Y-m-d H:i:s' ) ]);

		$this->repository->create( $token );

		$found = $this->repository->findByToken( $token->getToken() );

		$this->assertNotNull( $found );
		$this->assertEquals(
			$expiresAt->format( 'Y-m-d H:i:s' ),
			$found->getExpiresAt()->format( 'Y-m-d H:i:s' )
		);
	}
}
