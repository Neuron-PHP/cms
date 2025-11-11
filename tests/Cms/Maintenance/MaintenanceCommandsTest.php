<?php

namespace Tests\Cms\Maintenance;

use Neuron\Cms\Maintenance\MaintenanceManager;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for maintenance CLI commands
 * Tests the full workflow of enable -> status -> disable
 */
class MaintenanceCommandsTest extends TestCase
{
	private $root;
	private $basePath;
	private MaintenanceManager $manager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create virtual filesystem
		$this->root = vfsStream::setup( 'test' );
		$this->basePath = vfsStream::url( 'test' );
		$this->manager = new MaintenanceManager( $this->basePath );
	}

	/**
	 * Test complete maintenance mode workflow
	 */
	public function testCompleteMaintenanceWorkflow(): void
	{
		// 1. Initially disabled
		$this->assertFalse( $this->manager->isEnabled() );
		$this->assertNull( $this->manager->getStatus() );

		// 2. Enable maintenance mode (simulates cms:maintenance:enable)
		$message = 'Site under maintenance for testing';
		$allowedIps = ['127.0.0.1', '192.168.1.0/24'];
		$retryAfter = 1800;

		$enabled = $this->manager->enable( $message, $allowedIps, $retryAfter, 'testuser' );

		$this->assertTrue( $enabled );
		$this->assertTrue( $this->manager->isEnabled() );

		// 3. Check status (simulates cms:maintenance:status)
		$status = $this->manager->getStatus();

		$this->assertIsArray( $status );
		$this->assertTrue( $status['enabled'] );
		$this->assertEquals( $message, $status['message'] );
		$this->assertEquals( $allowedIps, $status['allowed_ips'] );
		$this->assertEquals( $retryAfter, $status['retry_after'] );
		$this->assertEquals( 'testuser', $status['enabled_by'] );
		$this->assertArrayHasKey( 'enabled_at', $status );

		// Verify message and retry-after getters
		$this->assertEquals( $message, $this->manager->getMessage() );
		$this->assertEquals( $retryAfter, $this->manager->getRetryAfter() );

		// 4. Verify IP checking works
		$this->assertTrue( $this->manager->isIpAllowed( '127.0.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.50' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '10.0.0.1' ) );

		// 5. Disable maintenance mode (simulates cms:maintenance:disable)
		$disabled = $this->manager->disable();

		$this->assertTrue( $disabled );
		$this->assertFalse( $this->manager->isEnabled() );
		$this->assertNull( $this->manager->getStatus() );
	}

	/**
	 * Test enable with minimal options (defaults)
	 */
	public function testEnableWithDefaults(): void
	{
		// Enable with just a message (simulates: cms:maintenance:enable -m "Message")
		$message = 'Quick maintenance';
		$this->manager->enable( $message );

		$this->assertTrue( $this->manager->isEnabled() );

		$status = $this->manager->getStatus();
		$this->assertEquals( $message, $status['message'] );
		$this->assertContains( '127.0.0.1', $status['allowed_ips'] );
		$this->assertContains( '::1', $status['allowed_ips'] );
		$this->assertNull( $status['retry_after'] );
	}

	/**
	 * Test status when maintenance is not enabled
	 */
	public function testStatusWhenNotEnabled(): void
	{
		// Simulates: cms:maintenance:status (when disabled)
		$this->assertFalse( $this->manager->isEnabled() );
		$this->assertNull( $this->manager->getStatus() );

		// Default message should still be available
		$message = $this->manager->getMessage();
		$this->assertIsString( $message );
		$this->assertNotEmpty( $message );
	}

	/**
	 * Test disabling when already disabled
	 */
	public function testDisableWhenAlreadyDisabled(): void
	{
		// Simulates: cms:maintenance:disable (when already disabled)
		$this->assertFalse( $this->manager->isEnabled() );

		$result = $this->manager->disable();
		$this->assertTrue( $result ); // Should succeed

		$this->assertFalse( $this->manager->isEnabled() );
	}

	/**
	 * Test multiple enable/disable cycles
	 */
	public function testMultipleEnableDisableCycles(): void
	{
		// Cycle 1
		$this->manager->enable( 'Cycle 1' );
		$this->assertTrue( $this->manager->isEnabled() );
		$this->manager->disable();
		$this->assertFalse( $this->manager->isEnabled() );

		// Cycle 2
		$this->manager->enable( 'Cycle 2', ['10.0.0.1'], 900 );
		$this->assertTrue( $this->manager->isEnabled() );
		$this->assertEquals( 'Cycle 2', $this->manager->getMessage() );
		$this->manager->disable();
		$this->assertFalse( $this->manager->isEnabled() );

		// Cycle 3
		$this->manager->enable( 'Cycle 3' );
		$this->assertTrue( $this->manager->isEnabled() );
		$this->manager->disable();
		$this->assertFalse( $this->manager->isEnabled() );
	}

	/**
	 * Test file persistence across manager instances
	 */
	public function testFilePersistenceAcrossInstances(): void
	{
		// Enable with first instance
		$message = 'Persistent maintenance';
		$this->manager->enable( $message, ['192.168.1.1'], 3600 );

		// Create new instance (simulates new command invocation)
		$newManager = new MaintenanceManager( $this->basePath );

		// Should still be enabled
		$this->assertTrue( $newManager->isEnabled() );
		$this->assertEquals( $message, $newManager->getMessage() );

		$status = $newManager->getStatus();
		$this->assertEquals( $message, $status['message'] );
		$this->assertContains( '192.168.1.1', $status['allowed_ips'] );
		$this->assertEquals( 3600, $status['retry_after'] );

		// Disable with new instance
		$newManager->disable();

		// Create another instance to verify it's disabled
		$thirdManager = new MaintenanceManager( $this->basePath );
		$this->assertFalse( $thirdManager->isEnabled() );
	}

	/**
	 * Test updating maintenance mode (re-enable with different settings)
	 */
	public function testUpdateMaintenanceMode(): void
	{
		// Initial enable
		$this->manager->enable( 'Initial message', ['127.0.0.1'], 1800 );

		$status1 = $this->manager->getStatus();
		$this->assertEquals( 'Initial message', $status1['message'] );
		$this->assertEquals( 1800, $status1['retry_after'] );

		// Update by re-enabling with different settings
		$this->manager->enable( 'Updated message', ['127.0.0.1', '10.0.0.0/8'], 3600 );

		$status2 = $this->manager->getStatus();
		$this->assertEquals( 'Updated message', $status2['message'] );
		$this->assertEquals( 3600, $status2['retry_after'] );
		$this->assertContains( '10.0.0.0/8', $status2['allowed_ips'] );
	}

	/**
	 * Test IP whitelist with various formats
	 */
	public function testIpWhitelistVariousFormats(): void
	{
		$allowedIps = [
			'192.168.1.100',        // Single IP
			'10.0.0.0/8',           // /8 CIDR
			'172.16.0.0/16',        // /16 CIDR
			'192.168.100.0/24',     // /24 CIDR
			'203.0.113.5',          // Another single IP
		];

		$this->manager->enable( 'Test IPs', $allowedIps );

		// Test single IPs
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.100' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '203.0.113.5' ) );

		// Test /8 CIDR
		$this->assertTrue( $this->manager->isIpAllowed( '10.0.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '10.255.255.254' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '11.0.0.1' ) );

		// Test /16 CIDR
		$this->assertTrue( $this->manager->isIpAllowed( '172.16.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '172.16.255.254' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '172.17.0.1' ) );

		// Test /24 CIDR
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.100.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.100.254' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '192.168.101.1' ) );
	}
}
