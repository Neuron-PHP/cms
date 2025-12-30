<?php

namespace Tests\Unit\Auth\Filters;

use Neuron\Cms\Auth\Filters\SecurityHeadersFilter;
use Neuron\Routing\RouteMap;
use PHPUnit\Framework\TestCase;

/**
 * Test SecurityHeadersFilter
 *
 * @package Tests\Unit\Auth\Filters
 */
class SecurityHeadersFilterTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Clear any existing headers (in case other tests set them)
		if( !headers_sent() )
		{
			header_remove();
		}
	}

	public function testDefaultConfiguration()
	{
		$filter = new SecurityHeadersFilter();
		$config = $filter->getConfig();

		// Check that default security headers are configured
		$this->assertArrayHasKey( 'X-Frame-Options', $config );
		$this->assertArrayHasKey( 'X-Content-Type-Options', $config );
		$this->assertArrayHasKey( 'X-XSS-Protection', $config );
		$this->assertArrayHasKey( 'Referrer-Policy', $config );
		$this->assertArrayHasKey( 'Content-Security-Policy', $config );
		$this->assertArrayHasKey( 'Strict-Transport-Security', $config );
		$this->assertArrayHasKey( 'Permissions-Policy', $config );

		// Check default values
		$this->assertEquals( 'DENY', $config['X-Frame-Options'] );
		$this->assertEquals( 'nosniff', $config['X-Content-Type-Options'] );
		$this->assertEquals( '1; mode=block', $config['X-XSS-Protection'] );
		$this->assertEquals( 'strict-origin-when-cross-origin', $config['Referrer-Policy'] );
	}

	public function testCustomConfiguration()
	{
		$customConfig = [
			'X-Frame-Options' => 'SAMEORIGIN',
			'Custom-Header' => 'custom-value',
		];

		$filter = new SecurityHeadersFilter( $customConfig );
		$config = $filter->getConfig();

		// Custom config should override defaults
		$this->assertEquals( 'SAMEORIGIN', $config['X-Frame-Options'] );
		$this->assertEquals( 'custom-value', $config['Custom-Header'] );

		// Default headers should still be present
		$this->assertArrayHasKey( 'X-Content-Type-Options', $config );
	}

	public function testSetHeader()
	{
		$filter = new SecurityHeadersFilter();
		$filter->setHeader( 'X-Frame-Options', 'SAMEORIGIN' );

		$config = $filter->getConfig();
		$this->assertEquals( 'SAMEORIGIN', $config['X-Frame-Options'] );
	}

	public function testRemoveHeader()
	{
		$filter = new SecurityHeadersFilter();
		$filter->removeHeader( 'X-Frame-Options' );

		$config = $filter->getConfig();
		$this->assertArrayNotHasKey( 'X-Frame-Options', $config );

		// Other headers should still be present
		$this->assertArrayHasKey( 'X-Content-Type-Options', $config );
	}

	public function testFluentInterface()
	{
		$filter = new SecurityHeadersFilter();

		$result = $filter
			->setHeader( 'X-Frame-Options', 'SAMEORIGIN' )
			->removeHeader( 'X-XSS-Protection' );

		// Should return same instance for chaining
		$this->assertSame( $filter, $result );
	}

	public function testContentSecurityPolicyDefault()
	{
		$filter = new SecurityHeadersFilter();
		$config = $filter->getConfig();

		$csp = $config['Content-Security-Policy'];

		// Should contain important directives
		$this->assertStringContainsString( "default-src 'self'", $csp );
		$this->assertStringContainsString( "script-src", $csp );
		$this->assertStringContainsString( "frame-ancestors 'none'", $csp );
	}

	public function testPermissionsPolicyDefault()
	{
		$filter = new SecurityHeadersFilter();
		$config = $filter->getConfig();

		$permissionsPolicy = $config['Permissions-Policy'];

		// Should restrict sensitive features
		$this->assertStringContainsString( 'geolocation=()', $permissionsPolicy );
		$this->assertStringContainsString( 'microphone=()', $permissionsPolicy );
		$this->assertStringContainsString( 'camera=()', $permissionsPolicy );
	}

	public function testStrictTransportSecurityConfiguration()
	{
		$filter = new SecurityHeadersFilter();
		$config = $filter->getConfig();

		$hsts = $config['Strict-Transport-Security'];

		// Should include max-age and includeSubDomains
		$this->assertStringContainsString( 'max-age=', $hsts );
		$this->assertStringContainsString( 'includeSubDomains', $hsts );
	}

	/**
	 * Test that filter can be instantiated and post method is callable
	 */
	public function testFilterInstantiation()
	{
		$filter = new SecurityHeadersFilter();

		// Create a mock RouteMap
		$routeMap = $this->createMock( RouteMap::class );

		// Should not throw exception when calling post
		$result = $filter->post( $routeMap );

		// Post filter returns null (headers are set as side effect)
		$this->assertNull( $result );
	}

	/**
	 * Test that pre filter is not used (returns null)
	 */
	public function testPreFilterNotUsed()
	{
		$filter = new SecurityHeadersFilter();
		$routeMap = $this->createMock( RouteMap::class );

		// Pre filter should return null (not used for security headers)
		$result = $filter->pre( $routeMap );
		$this->assertNull( $result );
	}
}
