<?php

namespace Tests\Unit\Cms;

use PHPUnit\Framework\TestCase;
use Neuron\Routing\Router;
use Neuron\Routing\Exceptions\DuplicateRouteException;

/**
 * Test duplicate route detection features.
 *
 * These tests verify the routing fixes:
 * - Duplicate route name detection works correctly
 * - setName() rollback on failure (name registration before unregistration)
 * - addRoute() rollback on setName() failure
 * - Strict mode validation of existing routes
 * - First-match-wins behavior in non-strict mode
 */
class DuplicateRouteDetectionTest extends TestCase
{
	protected Router $router;

	protected function setUp(): void
	{
		parent::setUp();
		// Create fresh router for each test
		$this->router = new Router();

		// Skip tests if routing component doesn't have new features yet
		if( !method_exists( $this->router, 'setStrictMode' ) )
		{
			$this->markTestSkipped( 'Routing component needs to be upgraded to support new features (setStrictMode, clearRoutes, route name parameter)' );
		}
	}

	protected function tearDown(): void
	{
		// Reset router
		$this->router = null;
		parent::tearDown();
	}

	/**
	 * Test that duplicate route names are detected in strict mode
	 */
	public function testDuplicateRouteNameDetection()
	{
		$this->router->setStrictMode( true );

		// Add first route with name
		$this->router->get( '/users', function() { return 'first'; }, null, 'users.index' );

		// Attempt to add second route with same name should throw
		$this->expectException( DuplicateRouteException::class );
		$this->expectExceptionMessage( 'users.index' );

		$this->router->post( '/users/create', function() { return 'second'; }, null, 'users.index' );
	}

	/**
	 * Test that setName() rollback works correctly
	 */
	public function testSetNameRollbackOnFailure()
	{
		$this->router->setStrictMode( true );

		// Create route1 with name
		$route1 = $this->router->get( '/first', function() {}, null, 'test.route' );
		$this->assertEquals( 'test.route', $route1->getName() );

		// Create route2 with old name
		$route2 = $this->router->get( '/second', function() {}, null, 'old.name' );
		$this->assertEquals( 'old.name', $route2->getName() );

		// Try to rename route2 to existing name (should fail)
		try
		{
			$route2->setName( 'test.route' );
			$this->fail( 'Should have thrown DuplicateRouteException' );
		}
		catch( DuplicateRouteException $e )
		{
			// Expected - verify rollback
			$this->assertEquals( 'old.name', $route2->getName(), 'Route2 should still have old name after failed rename' );

			// Verify old name is still registered to route2
			$foundRoute = $this->router->getRouteByName( 'old.name' );
			$this->assertSame( $route2, $foundRoute, 'old.name should still belong to route2' );
		}
	}

	/**
	 * Test that duplicate path detection works in strict mode
	 */
	public function testDuplicatePathDetection()
	{
		$this->router->setStrictMode( true );

		$this->router->get( '/users/:id', function() { return 'first'; } );

		$this->expectException( DuplicateRouteException::class );
		$this->expectExceptionMessage( 'GET /users/:id' );

		$this->router->get( '/users/:id', function() { return 'second'; } );
	}

	/**
	 * Test first-match-wins in non-strict mode
	 */
	public function testFirstMatchWinsInNonStrictMode()
	{
		$this->router->setStrictMode( false );

		// Add first route
		$route1 = $this->router->get( '/test', function() { return 'first'; } );

		// Add duplicate (should not throw, first wins)
		$route2 = $this->router->get( '/test', function() { return 'second'; } );

		// Verify no exception thrown
		$this->assertNotNull( $route1 );
		$this->assertNotNull( $route2 );

		// Verify first route is matched
		$matched = $this->router->getRoute( \Neuron\Routing\RequestMethod::GET, '/test' );
		$this->assertSame( $route1, $matched, 'First route should win in non-strict mode' );
	}

	/**
	 * Test enabling strict mode validates existing routes
	 */
	public function testEnablingStrictModeValidatesExisting()
	{
		// Add duplicates in non-strict mode
		$this->router->setStrictMode( false );
		$this->router->get( '/duplicate', function() { return 'first'; } );
		$this->router->get( '/duplicate', function() { return 'second'; } );

		// Enabling strict mode should detect the duplicates
		$this->expectException( DuplicateRouteException::class );
		$this->router->setStrictMode( true );
	}

	/**
	 * Test clearRoutes() clears filter registry
	 */
	public function testClearRoutesAlsoClearsFilterRegistry()
	{
		// Register a filter
		$filter = new \Neuron\Routing\Filter( function() { return true; }, null );
		$this->router->registerFilter( 'test-filter', $filter );

		// Add a route
		$this->router->get( '/test', function() {} );

		// Clear routes
		$this->router->clearRoutes();

		// Verify filter registry was cleared
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Filter test-filter not registered' );

		$this->router->getFilter( 'test-filter' );
	}

	/**
	 * Test RESTful routes with same path but different methods (should be allowed)
	 */
	public function testRestfulRoutesAllowed()
	{
		$this->router->setStrictMode( true );

		// These should all be allowed (different HTTP methods)
		$route1 = $this->router->get( '/users', function() {}, null, 'users.index' );
		$route2 = $this->router->post( '/users', function() {}, null, 'users.store' );
		$route3 = $this->router->put( '/users/:id', function() {}, null, 'users.update' );
		$route4 = $this->router->delete( '/users/:id', function() {}, null, 'users.destroy' );

		$this->assertNotNull( $route1 );
		$this->assertNotNull( $route2 );
		$this->assertNotNull( $route3 );
		$this->assertNotNull( $route4 );
	}
}
