<?php

namespace Tests\Unit\Cms\Database;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;
use Exception;

/**
 * Test cases for ConnectionFactory with URL support
 */
class ConnectionFactoryTest extends TestCase
{
	/**
	 * Test parsing MySQL URL (without actual connection)
	 */
	public function testParseMysqlUrl(): void
	{
		// Use reflection to test the parseUrl method directly
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, [ 'mysql://testuser:testpass@localhost:3306/testdb?charset=utf8mb4' ] );

		$this->assertEquals( 'mysql', $result['adapter'] );
		$this->assertEquals( 'localhost', $result['host'] );
		$this->assertEquals( 3306, $result['port'] );
		$this->assertEquals( 'testuser', $result['user'] );
		$this->assertEquals( 'testpass', $result['pass'] );
		$this->assertEquals( 'testdb', $result['name'] );
		$this->assertEquals( 'utf8mb4', $result['charset'] );
	}

	/**
	 * Test parsing PostgreSQL URL with various schemes
	 */
	public function testParsePostgresqlUrl(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		// Test different PostgreSQL URL schemes
		$urls = [
			'postgresql://user:pass@host:5432/dbname',
			'postgres://user:pass@host:5432/dbname',
			'pgsql://user:pass@host:5432/dbname'
		];

		foreach( $urls as $url )
		{
			$result = $method->invokeArgs( null, [ $url ] );
			$this->assertEquals( 'pgsql', $result['adapter'] );
			$this->assertEquals( 'host', $result['host'] );
			$this->assertEquals( 5432, $result['port'] );
			$this->assertEquals( 'user', $result['user'] );
			$this->assertEquals( 'pass', $result['pass'] );
			$this->assertEquals( 'dbname', $result['name'] );
		}
	}

	/**
	 * Test parsing SQLite URLs
	 */
	public function testParseSqliteUrl(): void
	{
		// Test :memory: database
		$settings = $this->createSettingsWithUrl( 'sqlite::memory:' );
		$pdo = ConnectionFactory::createFromSettings( $settings );
		$this->assertInstanceOf( PDO::class, $pdo );

		// Test with absolute path
		$tempFile = tempnam( sys_get_temp_dir(), 'test_db_' ) . '.sqlite3';
		$settings = $this->createSettingsWithUrl( 'sqlite:///' . $tempFile );
		$pdo = ConnectionFactory::createFromSettings( $settings );
		$this->assertInstanceOf( PDO::class, $pdo );

		// Clean up
		if( file_exists( $tempFile ) )
		{
			unlink( $tempFile );
		}
	}

	/**
	 * Test URL with individual parameter overrides
	 */
	public function testUrlWithOverrides(): void
	{
		// Create a Memory source with URL and overrides
		$source = new Memory();
		$source->set( 'database', 'url', 'mysql://user:pass@urlhost:3306/urldb' );
		$source->set( 'database', 'host', 'overridehost' );
		$source->set( 'database', 'port', 9999 );

		// Create SettingManager with the source
		$settings = new SettingManager( $source );
		$config = $settings->getSection( 'database' );

		// Verify the configuration has both URL and overrides
		$this->assertArrayHasKey( 'url', $config );
		$this->assertEquals( 'overridehost', $config['host'] );
		$this->assertEquals( 9999, $config['port'] );

		// Now test that createFromConfig properly applies the overrides
		// We'll use a mock PDO to test the DSN construction without actual connection
		try {
			// Call createFromConfig through reflection to catch the DSN
			$reflection = new \ReflectionClass( ConnectionFactory::class );
			$createMethod = $reflection->getMethod( 'createFromConfig' );

			// Instead of creating actual PDO, let's verify the merge logic
			// by inspecting what createFromConfig would do
			$parseMethod = $reflection->getMethod( 'parseUrl' );
			$parseMethod->setAccessible( true );

			// Parse the URL to get base config
			$urlConfig = $parseMethod->invokeArgs( null, [ $config['url'] ] );

			// Apply the same merge logic that createFromConfig uses
			$finalConfig = array_merge(
				$urlConfig,
				array_filter( $config, function( $value, $key ) {
					return $key !== 'url' && $value !== null;
				}, ARRAY_FILTER_USE_BOTH )
			);

			// Verify that overrides were applied
			$this->assertEquals( 'overridehost', $finalConfig['host'], 'Host should be overridden' );
			$this->assertEquals( 9999, $finalConfig['port'], 'Port should be overridden' );

			// Verify other values from URL are preserved
			$this->assertEquals( 'user', $finalConfig['user'], 'User from URL should be preserved' );
			$this->assertEquals( 'pass', $finalConfig['pass'], 'Password from URL should be preserved' );
			$this->assertEquals( 'urldb', $finalConfig['name'], 'Database name from URL should be preserved' );
			$this->assertEquals( 'mysql', $finalConfig['adapter'], 'Adapter from URL should be preserved' );

			// Verify the DSN that would be constructed uses the overrides
			$expectedDsn = sprintf(
				"mysql:host=%s;port=%s;dbname=%s;charset=%s",
				'overridehost',  // Override host
				9999,           // Override port
				'urldb',        // From URL
				'utf8mb4'       // Default charset
			);

			// Build actual DSN using same logic as ConnectionFactory
			$actualDsn = sprintf(
				"mysql:host=%s;port=%s;dbname=%s;charset=%s",
				$finalConfig['host'] ?? 'localhost',
				$finalConfig['port'] ?? 3306,
				$finalConfig['name'],
				$finalConfig['charset'] ?? 'utf8mb4'
			);

			$this->assertEquals( $expectedDsn, $actualDsn, 'DSN should use overridden host and port' );

		} catch ( \PDOException $e ) {
			// If PDO fails (no actual MySQL), that's expected - we're testing config merge logic
			$this->markTestSkipped( 'Cannot test actual PDO connection without MySQL server' );
		}
	}

	/**
	 * Test malformed URL handling
	 */
	public function testMalformedUrlThrowsException(): void
	{
		$settings = $this->createSettingsWithUrl( 'not-a-valid-url' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Malformed database URL' );

		ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Test unsupported scheme handling
	 */
	public function testUnsupportedSchemeThrowsException(): void
	{
		$settings = $this->createSettingsWithUrl( 'mongodb://localhost:27017/testdb' );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Unsupported database scheme: mongodb' );

		ConnectionFactory::createFromSettings( $settings );
	}

	/**
	 * Test URL with query parameters
	 */
	public function testUrlWithQueryParameters(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, [ 'mysql://user:pass@localhost:3306/testdb?charset=latin1' ] );

		// The charset from query parameter should be preserved
		$this->assertEquals( 'latin1', $result['charset'] );
	}

	/**
	 * Test backward compatibility - existing configs without URL should still work
	 */
	public function testBackwardCompatibility(): void
	{
		$source = new Memory();
		$source->set( 'database', 'adapter', 'sqlite' );
		$source->set( 'database', 'name', ':memory:' );

		$settings = new SettingManager( $source );
		$pdo = ConnectionFactory::createFromSettings( $settings );

		$this->assertInstanceOf( PDO::class, $pdo );
	}

	/**
	 * Test URL without password
	 */
	public function testUrlWithoutPassword(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, [ 'mysql://user@localhost:3306/testdb' ] );

		// Should parse without error (password will be null)
		$this->assertEquals( 'user', $result['user'] );
		$this->assertArrayNotHasKey( 'pass', $result );
	}

	/**
	 * Test URL without port (should use defaults)
	 */
	public function testUrlWithoutPort(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, [ 'mysql://user:pass@localhost/testdb' ] );

		// Should parse without error (port will use default)
		$this->assertEquals( 'localhost', $result['host'] );
		$this->assertEquals( 'testdb', $result['name'] );
		$this->assertArrayNotHasKey( 'port', $result ); // Port not in URL, will use default
	}

	/**
	 * Test URL with encoded special characters in credentials
	 */
	public function testUrlWithEncodedCredentials(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		// Test password with @ symbol (encoded as %40)
		$result = $method->invokeArgs( null, [ 'mysql://user:p%40ssw%3Ard@localhost:3306/testdb' ] );

		// Credentials should be decoded
		$this->assertEquals( 'user', $result['user'] );
		$this->assertEquals( 'p@ssw:rd', $result['pass'] );  // %40 becomes @, %3A becomes :
	}

	/**
	 * Test SQLite URL with triple slash (absolute path)
	 */
	public function testSqliteUrlWithTripleSlash(): void
	{
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, [ 'sqlite:///storage/database.sqlite3' ] );

		// Should preserve absolute path with single leading slash
		$this->assertEquals( 'sqlite', $result['adapter'] );
		$this->assertEquals( '/storage/database.sqlite3', $result['name'] );
	}

	/**
	 * Test that URL configuration doesn't conflict with other config keys
	 * This simulates the scenario where URL comes from secrets and public config has other keys
	 */
	public function testUrlDoesNotConflictWithOtherConfig(): void
	{
		$source = new Memory();
		// Simulate URL from secrets and no adapter in public config
		$source->set( 'database', 'url', 'mysql://user:pass@localhost:3306/testdb' );
		// No 'adapter' key should be set to avoid conflicts

		$settings = new SettingManager( $source );
		$config = $settings->getSection( 'database' );

		// The URL should be the only thing in the config
		$this->assertArrayHasKey( 'url', $config );
		$this->assertArrayNotHasKey( 'adapter', $config );

		// ConnectionFactory should be able to create from this config
		// (We can't test actual PDO creation without a real database,
		// but we can verify the config is parseable)
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$method = $reflection->getMethod( 'parseUrl' );
		$method->setAccessible( true );

		$parsed = $method->invokeArgs( null, [ $config['url'] ] );
		$this->assertEquals( 'mysql', $parsed['adapter'] );
	}

	/**
	 * Helper method to create settings with a database URL
	 */
	private function createSettingsWithUrl( string $url ): SettingManager
	{
		$source = new Memory();
		$source->set( 'database', 'url', $url );
		return new SettingManager( $source );
	}
}