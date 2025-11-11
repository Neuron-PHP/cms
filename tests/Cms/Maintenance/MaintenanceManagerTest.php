<?php

namespace Tests\Cms\Maintenance;

use Neuron\Cms\Maintenance\MaintenanceManager;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceManager functionality
 */
class MaintenanceManagerTest extends TestCase
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

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	/**
	 * Test that maintenance mode is disabled by default
	 */
	public function testMaintenanceModeDisabledByDefault(): void
	{
		$this->assertFalse( $this->manager->isEnabled() );
		$this->assertNull( $this->manager->getStatus() );
	}

	/**
	 * Test enabling maintenance mode
	 */
	public function testEnableMaintenanceMode(): void
	{
		$message = 'Test maintenance message';
		$allowedIps = ['192.168.1.1', '10.0.0.0/8'];
		$retryAfter = 3600;

		$result = $this->manager->enable( $message, $allowedIps, $retryAfter, 'testuser' );

		$this->assertTrue( $result );
		$this->assertTrue( $this->manager->isEnabled() );

		$status = $this->manager->getStatus();
		$this->assertIsArray( $status );
		$this->assertEquals( $message, $status['message'] );
		$this->assertEquals( $allowedIps, $status['allowed_ips'] );
		$this->assertEquals( $retryAfter, $status['retry_after'] );
		$this->assertEquals( 'testuser', $status['enabled_by'] );
		$this->assertArrayHasKey( 'enabled_at', $status );
	}

	/**
	 * Test enabling with default allowed IPs
	 */
	public function testEnableWithDefaultAllowedIps(): void
	{
		$this->manager->enable( 'Test message' );

		$status = $this->manager->getStatus();
		$this->assertContains( '127.0.0.1', $status['allowed_ips'] );
		$this->assertContains( '::1', $status['allowed_ips'] );
	}

	/**
	 * Test disabling maintenance mode
	 */
	public function testDisableMaintenanceMode(): void
	{
		// First enable
		$this->manager->enable( 'Test' );
		$this->assertTrue( $this->manager->isEnabled() );

		// Then disable
		$result = $this->manager->disable();

		$this->assertTrue( $result );
		$this->assertFalse( $this->manager->isEnabled() );
		$this->assertNull( $this->manager->getStatus() );
	}

	/**
	 * Test disabling when already disabled
	 */
	public function testDisableWhenAlreadyDisabled(): void
	{
		$result = $this->manager->disable();
		$this->assertTrue( $result );
	}

	/**
	 * Test getting maintenance message
	 */
	public function testGetMessage(): void
	{
		$message = 'Custom maintenance message';
		$this->manager->enable( $message );

		$this->assertEquals( $message, $this->manager->getMessage() );
	}

	/**
	 * Test getting message when disabled
	 */
	public function testGetMessageWhenDisabled(): void
	{
		$message = $this->manager->getMessage();
		$this->assertIsString( $message );
		$this->assertStringContainsString( 'maintenance', strtolower( $message ) );
	}

	/**
	 * Test getting retry-after value
	 */
	public function testGetRetryAfter(): void
	{
		$retryAfter = 7200;
		$this->manager->enable( 'Test', [], $retryAfter );

		$this->assertEquals( $retryAfter, $this->manager->getRetryAfter() );
	}

	/**
	 * Test getting retry-after when disabled
	 */
	public function testGetRetryAfterWhenDisabled(): void
	{
		$this->assertNull( $this->manager->getRetryAfter() );
	}

	/**
	 * Test IP address is allowed - exact match
	 */
	public function testIsIpAllowedExactMatch(): void
	{
		$allowedIps = ['192.168.1.100', '10.0.0.5'];
		$this->manager->enable( 'Test', $allowedIps );

		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.100' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '10.0.0.5' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '192.168.1.101' ) );
	}

	/**
	 * Test IP address is allowed - CIDR notation
	 */
	public function testIsIpAllowedCidr(): void
	{
		$allowedIps = ['192.168.1.0/24'];
		$this->manager->enable( 'Test', $allowedIps );

		// Should allow entire subnet
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.100' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.254' ) );

		// Should not allow outside subnet
		$this->assertFalse( $this->manager->isIpAllowed( '192.168.2.1' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '10.0.0.1' ) );
	}

	/**
	 * Test IP allowed when maintenance disabled
	 */
	public function testIsIpAllowedWhenDisabled(): void
	{
		// All IPs should be allowed when maintenance is disabled
		$this->assertTrue( $this->manager->isIpAllowed( '192.168.1.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '10.0.0.1' ) );
	}

	/**
	 * Test localhost is allowed by default
	 */
	public function testLocalhostAllowedByDefault(): void
	{
		$this->manager->enable( 'Test' ); // No explicit IPs

		$this->assertTrue( $this->manager->isIpAllowed( '127.0.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '::1' ) );
	}

	/**
	 * Test CIDR notation with /8 network
	 */
	public function testCidrSlash8Network(): void
	{
		$allowedIps = ['10.0.0.0/8'];
		$this->manager->enable( 'Test', $allowedIps );

		$this->assertTrue( $this->manager->isIpAllowed( '10.0.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '10.255.255.254' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '11.0.0.1' ) );
	}

	/**
	 * Test CIDR notation with /16 network
	 */
	public function testCidrSlash16Network(): void
	{
		$allowedIps = ['172.16.0.0/16'];
		$this->manager->enable( 'Test', $allowedIps );

		$this->assertTrue( $this->manager->isIpAllowed( '172.16.0.1' ) );
		$this->assertTrue( $this->manager->isIpAllowed( '172.16.255.254' ) );
		$this->assertFalse( $this->manager->isIpAllowed( '172.17.0.1' ) );
	}

	/**
	 * Test maintenance file is created
	 */
	public function testMaintenanceFileCreated(): void
	{
		$this->manager->enable( 'Test' );

		$filePath = $this->basePath . '/.maintenance.json';
		$this->assertTrue( file_exists( $filePath ) );

		$contents = file_get_contents( $filePath );
		$data = json_decode( $contents, true );

		$this->assertIsArray( $data );
		$this->assertTrue( $data['enabled'] );
	}

	/**
	 * Test maintenance file is deleted when disabled
	 */
	public function testMaintenanceFileDeletedWhenDisabled(): void
	{
		$this->manager->enable( 'Test' );
		$filePath = $this->basePath . '/.maintenance.json';
		$this->assertTrue( file_exists( $filePath ) );

		$this->manager->disable();
		$this->assertFalse( file_exists( $filePath ) );
	}

	/**
	 * Test JSON format in maintenance file
	 */
	public function testMaintenanceFileJsonFormat(): void
	{
		$message = 'Maintenance message';
		$allowedIps = ['192.168.1.1'];
		$retryAfter = 1800;

		$this->manager->enable( $message, $allowedIps, $retryAfter, 'admin' );

		$filePath = $this->basePath . '/.maintenance.json';
		$contents = file_get_contents( $filePath );
		$data = json_decode( $contents, true );

		$this->assertNotNull( $data );
		$this->assertEquals( true, $data['enabled'] );
		$this->assertEquals( $message, $data['message'] );
		$this->assertEquals( $allowedIps, $data['allowed_ips'] );
		$this->assertEquals( $retryAfter, $data['retry_after'] );
		$this->assertEquals( 'admin', $data['enabled_by'] );
		$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['enabled_at'] );
	}
}
