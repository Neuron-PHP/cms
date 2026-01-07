<?php

namespace Tests\Unit\Cms\Database;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Integration test for ConnectionFactory with various configuration scenarios
 */
class ConnectionFactoryIntegrationTest extends TestCase
{
	/**
	 * Test SQLite connection with URL from secrets (simulating --use-secrets flag)
	 */
	public function testSqliteUrlFromSecrets(): void
	{
		// Simulate runtime with URL in secrets, nothing in public config
		$secretsSource = new Memory();
		$secretsSource->set( 'database', 'url', 'sqlite::memory:' );

		$settings = new SettingManager( $secretsSource );

		// This should work without any "Unsupported database adapter" errors
		$pdo = ConnectionFactory::createFromSettings( $settings );

		$this->assertInstanceOf( PDO::class, $pdo );

		// Verify it's actually SQLite
		$driver = $pdo->getAttribute( PDO::ATTR_DRIVER_NAME );
		$this->assertEquals( 'sqlite', $driver );
	}

	/**
	 * Test that mixing URL in secrets with public config (non-database) works
	 */
	public function testUrlFromSecretsWithOtherPublicConfig(): void
	{
		// Public config has site settings but NO database section
		$publicSource = new Memory();
		$publicSource->set( 'site', 'name', 'My Site' );
		$publicSource->set( 'cache', 'enabled', true );

		// Secrets has database URL
		$secretsSource = new Memory();
		$secretsSource->set( 'database', 'url', 'sqlite::memory:' );

		// Combine like the real app does
		$settings = new SettingManager( $publicSource );
		$settings->addSource( $secretsSource, 'secrets' );

		// Should create connection successfully
		$pdo = ConnectionFactory::createFromSettings( $settings );
		$this->assertInstanceOf( PDO::class, $pdo );
	}

	/**
	 * Test traditional configuration still works (non-URL)
	 */
	public function testTraditionalConfigurationStillWorks(): void
	{
		$source = new Memory();
		$source->set( 'database', 'adapter', 'sqlite' );
		$source->set( 'database', 'name', ':memory:' );

		$settings = new SettingManager( $source );
		$pdo = ConnectionFactory::createFromSettings( $settings );

		$this->assertInstanceOf( PDO::class, $pdo );
		$this->assertEquals( 'sqlite', $pdo->getAttribute( PDO::ATTR_DRIVER_NAME ) );
	}

	/**
	 * Test that URL configuration works without secrets
	 */
	public function testUrlWithoutSecrets(): void
	{
		$source = new Memory();
		$source->set( 'database', 'url', 'sqlite::memory:' );

		$settings = new SettingManager( $source );
		$pdo = ConnectionFactory::createFromSettings( $settings );

		$this->assertInstanceOf( PDO::class, $pdo );
	}
}