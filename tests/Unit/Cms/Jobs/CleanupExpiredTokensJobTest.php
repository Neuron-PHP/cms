<?php

namespace Tests\Unit\Cms\Jobs;

use Neuron\Cms\Jobs\CleanupExpiredTokensJob;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CleanupExpiredTokensJobTest extends TestCase
{
	private string $dbPath;
	private PDO $pdo;

	protected function setUp(): void
	{
		$this->dbPath = sys_get_temp_dir() . '/neuron_jobs_tokens_' . uniqid() . '.db';

		$settings = new SettingManager( new Memory() );
		$settings->set( 'database', 'adapter', 'sqlite' );
		$settings->set( 'database', 'name', $this->dbPath );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$this->pdo = new PDO( "sqlite:{$this->dbPath}" );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec(
			"CREATE TABLE email_verification_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL,
				token VARCHAR(64) NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				expires_at TIMESTAMP NOT NULL
			)"
		);

		$this->pdo->exec(
			"CREATE TABLE password_reset_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				email VARCHAR(255) NOT NULL,
				token VARCHAR(64) NOT NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				expires_at TIMESTAMP NOT NULL
			)"
		);
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		unset( $this->pdo );

		if( file_exists( $this->dbPath ) )
		{
			unlink( $this->dbPath );
		}
	}

	private function insertEmailToken( string $token, string $expiresAt ): void
	{
		$stmt = $this->pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at) VALUES (1, ?, ?, ?)"
		);
		$stmt->execute( [ $token, '2020-01-01 00:00:00', $expiresAt ] );
	}

	private function insertResetToken( string $token, string $expiresAt ): void
	{
		$stmt = $this->pdo->prepare(
			"INSERT INTO password_reset_tokens (email, token, created_at, expires_at) VALUES ('a@b.com', ?, ?, ?)"
		);
		$stmt->execute( [ $token, '2020-01-01 00:00:00', $expiresAt ] );
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'cleanup_expired_tokens', ( new CleanupExpiredTokensJob() )->getName() );
	}

	public function testRunDeletesExpiredTokensOnly(): void
	{
		$past   = ( new \DateTimeImmutable( '-1 day' ) )->format( 'Y-m-d H:i:s' );
		$future = ( new \DateTimeImmutable( '+1 day' ) )->format( 'Y-m-d H:i:s' );

		$this->insertEmailToken( 'e_expired', $past );
		$this->insertEmailToken( 'e_valid', $future );
		$this->insertResetToken( 'r_expired', $past );
		$this->insertResetToken( 'r_valid', $future );

		$result = ( new CleanupExpiredTokensJob() )->run();

		$this->assertTrue( $result );

		$emailTokens = $this->pdo->query( "SELECT token FROM email_verification_tokens" )->fetchAll( PDO::FETCH_COLUMN );
		$resetTokens = $this->pdo->query( "SELECT token FROM password_reset_tokens" )->fetchAll( PDO::FETCH_COLUMN );

		$this->assertEquals( [ 'e_valid' ], $emailTokens );
		$this->assertEquals( [ 'r_valid' ], $resetTokens );
	}

	public function testRunReturnsFalseWhenSettingsMissing(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		$this->assertFalse( ( new CleanupExpiredTokensJob() )->run() );
	}
}
