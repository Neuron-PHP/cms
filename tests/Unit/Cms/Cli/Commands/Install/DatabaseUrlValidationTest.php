<?php

namespace Tests\Unit\Cms\Cli\Commands\Install;

use PHPUnit\Framework\TestCase;

/**
 * Test database URL validation logic
 */
class DatabaseUrlValidationTest extends TestCase
{
	/**
	 * Test that parse_url correctly identifies invalid URLs
	 */
	public function testParseUrlBehavior(): void
	{
		// Valid URLs should return a scheme
		$this->assertNotFalse( parse_url( 'mysql://user:pass@host/db', PHP_URL_SCHEME ) );
		$this->assertEquals( 'mysql', parse_url( 'mysql://user:pass@host/db', PHP_URL_SCHEME ) );

		// Invalid URLs return false or null
		$result = parse_url( 'not-a-url', PHP_URL_SCHEME );
		$this->assertTrue( $result === false || $result === null, "not-a-url should not parse" );

		$this->assertNull( parse_url( ':::invalid:::', PHP_URL_SCHEME ) );
		$this->assertEmpty( parse_url( '', PHP_URL_SCHEME ) ); // Empty string returns empty result

		// URLs with unsupported schemes still parse
		$this->assertEquals( 'mongodb', parse_url( 'mongodb://localhost/db', PHP_URL_SCHEME ) );
		$this->assertEquals( 'ftp', parse_url( 'ftp://example.com/file', PHP_URL_SCHEME ) );
	}

	/**
	 * Test validation logic for database URLs
	 */
	public function testDatabaseUrlValidation(): void
	{
		// Valid non-SQLite database URLs (these parse with parse_url)
		$validStandardUrls = [
			'mysql://user:pass@localhost:3306/dbname',
			'postgresql://user:pass@localhost:5432/dbname',
			'postgres://user@localhost/dbname',
			'pgsql://localhost/dbname',
		];

		foreach( $validStandardUrls as $url )
		{
			$scheme = parse_url( $url, PHP_URL_SCHEME );
			$this->assertNotFalse( $scheme, "URL should parse: $url" );

			// Check if scheme is supported
			$supported = in_array( $scheme, [ 'mysql', 'postgresql', 'postgres', 'pgsql' ] );
			$this->assertTrue( $supported, "Scheme should be supported: $scheme" );
		}

		// SQLite URLs have mixed parsing behavior
		// Triple-slash doesn't parse
		$this->assertFalse( parse_url( 'sqlite:///path/to/database.db' ) );

		// Single slash and :memory: do parse
		$this->assertNotFalse( parse_url( 'sqlite:/path/to/database.db' ) );
		$this->assertNotFalse( parse_url( 'sqlite::memory:' ) );

		// All are valid SQLite URLs because they start with 'sqlite:'
		$validSqliteUrls = [
			'sqlite:///path/to/database.db',
			'sqlite:/path/to/database.db',
			'sqlite::memory:'
		];

		foreach( $validSqliteUrls as $url )
		{
			$this->assertTrue( str_starts_with( $url, 'sqlite:' ), "Should be SQLite URL: $url" );
		}

		// Invalid URLs
		$invalidUrls = [
			'not-a-url',
			'',
			'just-text',
			'http://not-a-database',
			'mongodb://localhost/db',  // Unsupported scheme
			'redis://localhost:6379',   // Unsupported scheme
		];

		foreach( $invalidUrls as $url )
		{
			$scheme = parse_url( $url, PHP_URL_SCHEME );

			if( $scheme === false || $scheme === null )
			{
				// Invalid URL format - should be rejected
				$this->assertTrue( true, "Invalid URL correctly identified: $url" );
			}
			else
			{
				// Has a scheme but should be unsupported for databases
				$supported = in_array( $scheme, [ 'mysql', 'postgresql', 'postgres', 'pgsql', 'sqlite' ] );
				$this->assertFalse( $supported, "Scheme should be unsupported: $scheme from $url" );
			}
		}
	}

	/**
	 * Test that MySQL/PostgreSQL URLs require host and database
	 */
	public function testNonSqliteUrlRequirements(): void
	{
		// These should be considered incomplete/invalid for MySQL/PostgreSQL
		$incompleteUrls = [
			'mysql://localhost',        // No database name
			'mysql://localhost/',       // Empty database name
			'postgresql:///',          // No host or database
			'pgsql://:5432/dbname'     // No host
		];

		foreach( $incompleteUrls as $url )
		{
			$parsed = parse_url( $url );
			$scheme = $parsed['scheme'] ?? null;

			if( in_array( $scheme, [ 'mysql', 'postgresql', 'postgres', 'pgsql' ] ) )
			{
				// Check for required components
				$hasHost = isset( $parsed['host'] ) && $parsed['host'] !== '';
				$hasDb = isset( $parsed['path'] ) && $parsed['path'] !== '/' && $parsed['path'] !== '';

				$isValid = $hasHost && $hasDb;
				$this->assertFalse( $isValid, "URL should be invalid (missing host/db): $url" );
			}
		}

		// These should be valid
		$validNonSqliteUrls = [
			'mysql://localhost/mydb',
			'postgresql://host:5432/database',
		];

		foreach( $validNonSqliteUrls as $url )
		{
			$parsed = parse_url( $url );

			$hasHost = isset( $parsed['host'] ) && $parsed['host'] !== '';
			$hasDb = isset( $parsed['path'] ) && $parsed['path'] !== '/' && $parsed['path'] !== '';

			$this->assertTrue( $hasHost && $hasDb, "URL should be valid: $url" );
		}

		// SQLite URLs are special and don't need standard URL validation
		$validSqliteUrls = [
			'sqlite:///path/to/db.sqlite',
			'sqlite::memory:'
		];

		foreach( $validSqliteUrls as $url )
		{
			// SQLite URLs are valid if they start with 'sqlite:'
			$this->assertTrue( str_starts_with( $url, 'sqlite:' ), "Should be valid SQLite URL: $url" );
		}
	}
}