<?php

namespace Tests\Cms\Maintenance;

use Neuron\Cms\Maintenance\MaintenanceManager;
use Neuron\Cms\Maintenance\MaintenanceFilter;
use Neuron\Routing\RouteMap;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceFilter functionality
 */
class MaintenanceFilterTest extends TestCase
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

		// Clear any server variables
		$_SERVER = [];
	}

	protected function tearDown(): void
	{
		$_SERVER = [];
		parent::tearDown();
	}

	/**
	 * Test filter allows requests when maintenance is disabled
	 */
	public function testFilterAllowsWhenMaintenanceDisabled(): void
	{
		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNull( $result ); // Null means continue to route
	}

	/**
	 * Test filter blocks requests when maintenance is enabled
	 */
	public function testFilterBlocksWhenMaintenanceEnabled(): void
	{
		$this->manager->enable( 'Test maintenance' );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.5'; // Not in allowed list

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNotNull( $result ); // Should return maintenance page
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'maintenance', strtolower( $result ) );
	}

	/**
	 * Test filter allows whitelisted IPs during maintenance
	 */
	public function testFilterAllowsWhitelistedIps(): void
	{
		$allowedIp = '192.168.1.100';
		$this->manager->enable( 'Test', [$allowedIp] );

		$_SERVER['REMOTE_ADDR'] = $allowedIp;

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNull( $result ); // Should allow through
	}

	/**
	 * Test filter allows localhost during maintenance
	 */
	public function testFilterAllowsLocalhost(): void
	{
		$this->manager->enable( 'Test' ); // Default includes 127.0.0.1

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNull( $result ); // Should allow localhost
	}

	/**
	 * Test filter maintenance page contains message
	 */
	public function testFilterMaintenancePageContainsMessage(): void
	{
		$customMessage = 'Custom maintenance message';
		$this->manager->enable( $customMessage );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertStringContainsString( $customMessage, $result );
	}

	/**
	 * Test filter respects X-Forwarded-For header
	 */
	public function testFilterRespectsXForwardedFor(): void
	{
		$allowedIp = '192.168.1.50';
		$this->manager->enable( 'Test', [$allowedIp] );

		$_SERVER['REMOTE_ADDR'] = '10.0.0.1'; // Proxy IP
		$_SERVER['HTTP_X_FORWARDED_FOR'] = $allowedIp; // Real client IP

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNull( $result ); // Should allow based on X-Forwarded-For
	}

	/**
	 * Test filter respects X-Real-IP header
	 */
	public function testFilterRespectsXRealIp(): void
	{
		$allowedIp = '192.168.1.75';
		$this->manager->enable( 'Test', [$allowedIp] );

		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_REAL_IP'] = $allowedIp;

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertNull( $result );
	}

	/**
	 * Test filter with custom view
	 */
	public function testFilterWithCustomView(): void
	{
		$customViewContent = '<html><body>Custom Maintenance Page</body></html>';

		$customView = vfsStream::newFile( 'custom-maintenance.php' )
			->at( $this->root )
			->setContent( $customViewContent );

		$this->manager->enable( 'Test' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$filter = new MaintenanceFilter( $this->manager, $customView->url() );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertStringContainsString( 'Custom Maintenance Page', $result );
	}

	/**
	 * Test default maintenance page structure
	 */
	public function testDefaultMaintenancePageStructure(): void
	{
		$this->manager->enable( 'Test message', [], 3600 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		// Check for HTML structure
		$this->assertStringContainsString( '<!DOCTYPE html>', $result );
		$this->assertStringContainsString( '<html', $result );
		$this->assertStringContainsString( '</html>', $result );
		$this->assertStringContainsString( 'Test message', $result );

		// Check for maintenance-related text
		$this->assertStringContainsString( 'maintenance', strtolower( $result ) );
	}

	/**
	 * Test maintenance page includes meta tags
	 */
	public function testMaintenancePageIncludesMetaTags(): void
	{
		$this->manager->enable( 'Test' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		$this->assertStringContainsString( '<meta', $result );
		$this->assertStringContainsString( 'viewport', $result );
		$this->assertStringContainsString( 'noindex', strtolower( $result ) );
	}

	/**
	 * Test maintenance page with retry-after shows estimate
	 */
	public function testMaintenancePageShowsRetryEstimate(): void
	{
		$this->manager->enable( 'Test', [], 3600 ); // 1 hour
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$filter = new MaintenanceFilter( $this->manager );
		$route = $this->createMockRoute();

		$result = $filter->pre( $route );

		// Should mention time estimate
		$this->assertMatchesRegularExpression( '/\d+\s+(hour|minute)/i', $result );
	}

	/**
	 * Create a mock RouteMap object
	 */
	private function createMockRoute(): RouteMap
	{
		return new RouteMap(
			'/test',
			function() { return 'test'; }
		);
	}
}
