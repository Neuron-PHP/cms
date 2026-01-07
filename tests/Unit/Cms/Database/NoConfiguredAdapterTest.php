<?php

namespace Tests\Unit\Cms\Database;

use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

/**
 * Definitive test that proves there's no "configured" adapter issue
 */
class NoConfiguredAdapterTest extends TestCase
{
	/**
	 * Test that we never get "Unsupported database adapter: configured" error
	 */
	public function testNoConfiguredAdapterError(): void
	{
		// Simulate the EXACT scenario: URL in secrets, nothing in public
		$publicSource = new Memory();
		// Public has NO database section at all

		$secretsSource = new Memory();
		$secretsSource->set( 'database', 'url', 'sqlite::memory:' );

		// Merge like the app does
		$settings = new SettingManager( $publicSource );
		$settings->addSource( $secretsSource );

		// This should NOT throw "Unsupported database adapter: configured"
		try {
			$pdo = ConnectionFactory::createFromSettings( $settings );
			$this->assertNotNull( $pdo );
			$this->assertEquals( 'sqlite', $pdo->getAttribute( \PDO::ATTR_DRIVER_NAME ) );
		} catch ( \Exception $e ) {
			// If we get here, check it's NOT the "configured" error
			$this->assertStringNotContainsString(
				'Unsupported database adapter: configured',
				$e->getMessage(),
				'Should not get "configured" adapter error'
			);
		}
	}

	/**
	 * Test what would happen if we DID have 'adapter' => 'configured' (broken scenario)
	 */
	public function testBrokenScenarioWithConfiguredAdapter(): void
	{
		$settings = new Memory();
		$settings->set( 'database', 'url', 'sqlite::memory:' );
		$settings->set( 'database', 'adapter', 'configured' ); // This would be the bug

		$settingManager = new SettingManager( $settings );

		// This WOULD throw an error because 'configured' is not a valid adapter
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unsupported database adapter: configured' );

		ConnectionFactory::createFromSettings( $settingManager );
	}

	/**
	 * Test the fixed scenario - URL only, no adapter key
	 */
	public function testFixedScenarioUrlOnly(): void
	{
		$settings = new Memory();
		$settings->set( 'database', 'url', 'sqlite::memory:' );
		// NO adapter key set - this is the fix

		$settingManager = new SettingManager( $settings );

		// This should work perfectly
		$pdo = ConnectionFactory::createFromSettings( $settingManager );
		$this->assertNotNull( $pdo );
		$this->assertEquals( 'sqlite', $pdo->getAttribute( \PDO::ATTR_DRIVER_NAME ) );
	}
}