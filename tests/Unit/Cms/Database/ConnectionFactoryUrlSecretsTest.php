<?php

namespace Tests\Unit\Cms\Database;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

/**
 * Test that URL + secrets configuration works correctly
 * This specifically tests the scenario where database URL comes from secrets
 * and ensures no adapter conflicts occur.
 */
class ConnectionFactoryUrlSecretsTest extends TestCase
{
	/**
	 * Test that URL from secrets works without adapter conflicts
	 * Simulates the exact runtime scenario when using --use-secrets flag
	 */
	public function testUrlFromSecretsWithoutAdapterConflict(): void
	{
		// Simulate what happens at runtime with merged config
		// (SettingManagerFactory does deep merge at boot time)
		$mergedConfig = [
			'site' => [
				'name' => 'Test Site'
			],
			'database' => [
				'url' => 'mysql://user:pass@localhost:3306/testdb'
			]
		];

		$source = new Memory( $mergedConfig );
		$settings = new SettingManager();
		$settings->setSource( $source );

		// Get the merged database config
		$config = $settings->getSection( 'database' );

		// Should only have URL, no adapter key to cause conflicts
		$this->assertIsArray( $config, 'Database config should be an array' );
		$this->assertArrayHasKey( 'url', $config );
		$this->assertArrayNotHasKey( 'adapter', $config );
		$this->assertCount( 1, $config, 'Database config should only contain URL' );

		// Now test that ConnectionFactory can parse this correctly
		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$parseMethod = $reflection->getMethod( 'parseUrl' );
		$parseMethod->setAccessible( true );

		$parsed = $parseMethod->invokeArgs( null, [ $config['url'] ] );

		// Verify the adapter is correctly extracted from URL
		$this->assertEquals( 'mysql', $parsed['adapter'] );
		$this->assertEquals( 'localhost', $parsed['host'] );
		$this->assertEquals( 3306, $parsed['port'] );
		$this->assertEquals( 'testdb', $parsed['name'] );
		$this->assertEquals( 'user', $parsed['user'] );
		$this->assertEquals( 'pass', $parsed['pass'] );
	}

	/**
	 * Test that old broken configuration would fail (for comparison)
	 * This demonstrates what would happen if we had 'adapter' => 'configured'
	 */
	public function testBrokenConfigurationWithPlaceholderAdapter(): void
	{
		$source = new Memory();
		// This simulates the BROKEN configuration that would cause the error
		$source->set( 'database', 'url', 'mysql://user:pass@localhost:3306/testdb' );
		$source->set( 'database', 'adapter', 'configured' ); // This would break it!

		$settings = new SettingManager( $source );
		$config = $settings->getSection( 'database' );

		// Both keys would be present
		$this->assertArrayHasKey( 'url', $config );
		$this->assertArrayHasKey( 'adapter', $config );

		// The adapter would be the broken placeholder
		$this->assertEquals( 'configured', $config['adapter'] );

		// This configuration would cause "Unsupported database adapter: configured" error
		// because the placeholder would override the URL-parsed adapter
	}

	/**
	 * Test the actual merging logic that happens in createFromConfig
	 */
	public function testCreateFromConfigMergingLogic(): void
	{
		// Test the exact merging that happens in ConnectionFactory::createFromConfig
		$config = [ 'url' => 'postgresql://user:pass@host:5432/dbname' ];

		$reflection = new \ReflectionClass( ConnectionFactory::class );
		$parseMethod = $reflection->getMethod( 'parseUrl' );
		$parseMethod->setAccessible( true );

		// Parse URL
		$urlConfig = $parseMethod->invokeArgs( null, [ $config['url'] ] );

		// Simulate the merge that happens in createFromConfig
		$mergedConfig = array_merge(
			$urlConfig,
			array_filter( $config, function( $value, $key ) {
				return $key !== 'url' && $value !== null;
			}, ARRAY_FILTER_USE_BOTH )
		);

		// The adapter from URL should be preserved (no override)
		$this->assertEquals( 'pgsql', $mergedConfig['adapter'] );
		$this->assertEquals( 'host', $mergedConfig['host'] );
		$this->assertEquals( 5432, $mergedConfig['port'] );
		$this->assertEquals( 'dbname', $mergedConfig['name'] );
	}
}