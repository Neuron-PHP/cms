<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Base class for integration tests.
 *
 * Provides real database setup with actual migrations.
 * Unlike unit tests with mocks or in-memory SQLite, these tests:
 * - Use a real test database
 * - Run actual Phinx migrations
 * - Test real infrastructure
 * - Catch migration issues
 * - Test database-specific features
 *
 * Usage:
 *   class MyIntegrationTest extends IntegrationTestCase
 *   {
 *       public function testSomething()
 *       {
 *           // $this->pdo is available - real database connection
 *           // All migrations have been run
 *       }
 *   }
 *
 * @package Tests\Integration
 */
abstract class IntegrationTestCase extends TestCase
{
	protected PDO $pdo;
	protected Manager $migrationManager;
	private static bool $migrationsRun = false;

	/**
	 * Set up test database before each test class
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
	}

	/**
	 * Set up test database and run migrations
	 */
	protected function setUp(): void
	{
		parent::setUp();

		// Create test database connection
		$this->pdo = $this->createTestDatabase();

		// Run migrations only once per test run
		if( !self::$migrationsRun )
		{
			$this->runMigrations();
			self::$migrationsRun = true;
		}

		// NOTE: We don't start a transaction here because repositories
		// manage their own transactions. Using both causes "already in transaction" errors.
	}

	/**
	 * Clean up after each test
	 */
	protected function tearDown(): void
	{
		// Clean up any active transactions from failed tests
		while( $this->pdo->inTransaction() )
		{
			$this->pdo->rollBack();
		}

		// Truncate all tables except phinxlog for test isolation
		$this->cleanupTestData();

		parent::tearDown();
	}

	/**
	 * Clean up test data by truncating tables
	 */
	private function cleanupTestData(): void
	{
		$driver = $this->pdo->getAttribute( PDO::ATTR_DRIVER_NAME );

		// Get list of tables
		if( $driver === 'sqlite' )
		{
			$tables = $this->pdo->query(
				"SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('phinxlog', 'sqlite_sequence')"
			)->fetchAll( PDO::FETCH_COLUMN );
		}
		elseif( $driver === 'mysql' )
		{
			$dbname = $this->getDatabaseName();
			$tables = $this->pdo->query(
				"SELECT table_name FROM information_schema.tables WHERE table_schema = '{$dbname}' AND table_name != 'phinxlog'"
			)->fetchAll( PDO::FETCH_COLUMN );
		}
		elseif( $driver === 'pgsql' )
		{
			$tables = $this->pdo->query(
				"SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename != 'phinxlog'"
			)->fetchAll( PDO::FETCH_COLUMN );
		}
		else
		{
			return;
		}

		// Disable foreign key checks temporarily
		if( $driver === 'sqlite' )
		{
			$this->pdo->exec( 'PRAGMA foreign_keys = OFF' );
		}
		elseif( $driver === 'mysql' )
		{
			$this->pdo->exec( 'SET FOREIGN_KEY_CHECKS = 0' );
		}

		// Truncate all tables
		foreach( $tables as $table )
		{
			if( $driver === 'sqlite' )
			{
				$this->pdo->exec( "DELETE FROM {$table}" );
			}
			else
			{
				$this->pdo->exec( "TRUNCATE TABLE {$table}" );
			}
		}

		// Re-enable foreign key checks
		if( $driver === 'sqlite' )
		{
			$this->pdo->exec( 'PRAGMA foreign_keys = ON' );
		}
		elseif( $driver === 'mysql' )
		{
			$this->pdo->exec( 'SET FOREIGN_KEY_CHECKS = 1' );
		}
	}

	/**
	 * Create test database connection
	 *
	 * Uses environment variables or defaults to SQLite file for testing.
	 * Set TEST_DB_DRIVER, TEST_DB_HOST, TEST_DB_NAME, etc. to use MySQL/PostgreSQL.
	 *
	 * @return PDO
	 */
	private function createTestDatabase(): PDO
	{
		$driver = getenv( 'TEST_DB_DRIVER' ) ?: 'sqlite';

		if( $driver === 'sqlite' )
		{
			// Use SQLite file for persistence across test methods
			$dbPath = sys_get_temp_dir() . '/cms_test_' . getmypid() . '.db';

			// Remove old test database if exists
			if( file_exists( $dbPath ) && !self::$migrationsRun )
			{
				unlink( $dbPath );
			}

			$pdo = new PDO(
				"sqlite:{$dbPath}",
				null,
				null,
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
				]
			);

			// Enable foreign keys for SQLite
			$pdo->exec( 'PRAGMA foreign_keys = ON' );

			// Enable WAL mode for better concurrency (matches ConnectionFactory)
			$pdo->exec( 'PRAGMA journal_mode = WAL' );

			// Set busy timeout to handle locks gracefully (matches ConnectionFactory)
			$pdo->exec( 'PRAGMA busy_timeout = 5000' );

			return $pdo;
		}

		if( $driver === 'mysql' )
		{
			$host = getenv( 'TEST_DB_HOST' ) ?: 'localhost';
			$port = getenv( 'TEST_DB_PORT' ) ?: '3306';
			$dbname = getenv( 'TEST_DB_NAME' ) ?: 'cms_test';
			$user = getenv( 'TEST_DB_USER' ) ?: 'root';
			$pass = getenv( 'TEST_DB_PASSWORD' ) ?: '';

			// Create database if it doesn't exist
			$pdo = new PDO(
				"mysql:host={$host};port={$port}",
				$user,
				$pass,
				[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
			);
			$pdo->exec( "CREATE DATABASE IF NOT EXISTS `{$dbname}`" );
			$pdo->exec( "USE `{$dbname}`" );

			return $pdo;
		}

		if( $driver === 'pgsql' )
		{
			$host = getenv( 'TEST_DB_HOST' ) ?: 'localhost';
			$port = getenv( 'TEST_DB_PORT' ) ?: '5432';
			$dbname = getenv( 'TEST_DB_NAME' ) ?: 'cms_test';
			$user = getenv( 'TEST_DB_USER' ) ?: 'postgres';
			$pass = getenv( 'TEST_DB_PASSWORD' ) ?: '';

			return new PDO(
				"pgsql:host={$host};port={$port};dbname={$dbname}",
				$user,
				$pass,
				[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
			);
		}

		throw new \RuntimeException( "Unsupported database driver: {$driver}" );
	}

	/**
	 * Run Phinx migrations
	 *
	 * This runs the ACTUAL migration files from resources/database/migrate/
	 * exactly as they would run in production.
	 */
	private function runMigrations(): void
	{
		$migrationsPath = dirname( __DIR__, 2 ) . '/resources/database/migrate';

		if( !is_dir( $migrationsPath ) )
		{
			throw new \RuntimeException( "Migrations directory not found: {$migrationsPath}" );
		}

		// Configure Phinx
		$config = new Config([
			'paths' => [
				'migrations' => $migrationsPath
			],
			'environments' => [
				'default_migration_table' => 'phinxlog',
				'default_environment' => 'test',
				'test' => [
					'name' => $this->getDatabaseName(),
					'connection' => $this->pdo
				]
			]
		]);

		// Create migration manager - use console output to see errors
		$output = new \Symfony\Component\Console\Output\ConsoleOutput();
		$this->migrationManager = new Manager( $config, new StringInput( '' ), $output );

		try
		{
			// Run all migrations
			$this->migrationManager->migrate( 'test' );
		}
		catch( \Exception $e )
		{
			throw new \RuntimeException( "Migration failed: " . $e->getMessage(), 0, $e );
		}
	}

	/**
	 * Get database name from PDO connection
	 */
	private function getDatabaseName(): string
	{
		$driver = $this->pdo->getAttribute( PDO::ATTR_DRIVER_NAME );

		if( $driver === 'sqlite' )
		{
			return 'test';
		}

		if( $driver === 'mysql' )
		{
			$result = $this->pdo->query( 'SELECT DATABASE()' )->fetchColumn();
			return $result ?: 'cms_test';
		}

		if( $driver === 'pgsql' )
		{
			$result = $this->pdo->query( 'SELECT current_database()' )->fetchColumn();
			return $result ?: 'cms_test';
		}

		return 'test';
	}

	/**
	 * Rollback all migrations (for cleanup)
	 *
	 * WARNING: This drops all tables. Only use in test teardown.
	 */
	protected function rollbackMigrations(): void
	{
		if( $this->migrationManager )
		{
			$this->migrationManager->rollback( 'test', 0 );
		}
	}

	/**
	 * Helper: Insert a test user and return the ID
	 */
	protected function createTestUser( array $data = [] ): int
	{
		$defaults = [
			'username' => 'testuser_' . uniqid(),
			'email' => 'test_' . uniqid() . '@example.com',
			'password_hash' => password_hash( 'password', PASSWORD_DEFAULT ),
			'role' => 'subscriber',
			'status' => 'active',
			'email_verified' => 1,
			'created_at' => date( 'Y-m-d H:i:s' ),
			'updated_at' => date( 'Y-m-d H:i:s' )
		];

		$userData = array_merge( $defaults, $data );

		$fields = implode( ', ', array_keys( $userData ) );
		$placeholders = implode( ', ', array_fill( 0, count( $userData ), '?' ) );

		$stmt = $this->pdo->prepare(
			"INSERT INTO users ({$fields}) VALUES ({$placeholders})"
		);
		$stmt->execute( array_values( $userData ) );

		return (int)$this->pdo->lastInsertId();
	}

	/**
	 * Helper: Truncate a table for cleanup
	 */
	protected function truncateTable( string $table ): void
	{
		$driver = $this->pdo->getAttribute( PDO::ATTR_DRIVER_NAME );

		if( $driver === 'sqlite' )
		{
			$this->pdo->exec( "DELETE FROM {$table}" );
		}
		else
		{
			$this->pdo->exec( "TRUNCATE TABLE {$table}" );
		}
	}
}
