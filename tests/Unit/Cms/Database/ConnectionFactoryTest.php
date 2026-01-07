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
		$source = new Memory();
		// Set URL
		$source->set( 'database', 'url', 'mysql://user:pass@urlhost:3306/urldb' );
		// Override specific parameters
		$source->set( 'database', 'host', 'overridehost' );
		$source->set( 'database', 'port', 9999 );

		$settings = new SettingManager( $source );
		$config = $settings->getSection( 'database' );

		// Individual parameters should take precedence
		$this->assertEquals( 'overridehost', $config['host'] );
		$this->assertEquals( 9999, $config['port'] );
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
	 * Helper method to create settings with a database URL
	 */
	private function createSettingsWithUrl( string $url ): SettingManager
	{
		$source = new Memory();
		$source->set( 'database', 'url', $url );
		return new SettingManager( $source );
	}

	/**
	 * Helper to assert that a callable does not throw an exception
	 */
	private function assertDoesNotThrow( callable $callable ): void
	{
		try
		{
			$callable();
			$this->assertTrue( true ); // Test passed
		}
		catch( \Exception $e )
		{
			$this->fail( 'Expected no exception, but got: ' . $e->getMessage() );
		}
	}
}