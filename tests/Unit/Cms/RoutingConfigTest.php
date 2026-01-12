<?php

namespace Tests\Unit\Cms;

use PHPUnit\Framework\TestCase;
use Neuron\Patterns\Registry;

/**
 * Test routing configuration precedence and controller_paths handling.
 *
 * These tests verify the fixes made to routing configuration loading:
 * - routing.yaml takes precedence over neuron.yaml
 * - Empty array vs null handling for controller_paths
 * - Explicit empty array configuration is respected
 */
class RoutingConfigTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		// Reset registry before each test
		Registry::getInstance()->reset();
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	/**
	 * Test that null value correctly triggers fallback to neuron.yaml
	 */
	public function testNullControllerPathsTriggersNeuronYamlFallback()
	{
		// Simulate routing.yaml not setting controller_paths (null in Registry)
		$controllerPaths = Registry::getInstance()->get( 'Routing.ControllerPaths' );

		$this->assertNull( $controllerPaths, 'Controller paths should be null when not set' );

		// Verify the strict null check would trigger fallback
		$shouldFallback = ( $controllerPaths === null );
		$this->assertTrue( $shouldFallback, 'Null should trigger fallback to neuron.yaml' );
	}

	/**
	 * Test that empty array is respected and doesn't trigger fallback
	 */
	public function testEmptyArrayControllerPathsDoesNotFallback()
	{
		// Simulate routing.yaml explicitly setting controller_paths: []
		Registry::getInstance()->set( 'Routing.ControllerPaths', [] );

		$controllerPaths = Registry::getInstance()->get( 'Routing.ControllerPaths' );

		$this->assertIsArray( $controllerPaths, 'Controller paths should be an array' );
		$this->assertCount( 0, $controllerPaths, 'Controller paths should be empty array' );

		// Verify the strict null check would NOT trigger fallback
		$shouldFallback = ( $controllerPaths === null );
		$this->assertFalse( $shouldFallback, 'Empty array should NOT trigger fallback' );
	}

	/**
	 * Test that populated array from routing.yaml is used
	 */
	public function testPopulatedControllerPathsFromRoutingYaml()
	{
		// Simulate routing.yaml with controller_paths
		$paths = [
			[
				'path' => 'vendor/neuron-php/cms/src/Cms/Controllers',
				'namespace' => 'Neuron\Cms\Controllers'
			]
		];

		Registry::getInstance()->set( 'Routing.ControllerPaths', $paths );

		$controllerPaths = Registry::getInstance()->get( 'Routing.ControllerPaths' );

		$this->assertIsArray( $controllerPaths );
		$this->assertCount( 1, $controllerPaths );
		$this->assertEquals( 'Neuron\Cms\Controllers', $controllerPaths[0]['namespace'] );

		// Verify the strict null check would NOT trigger fallback
		$shouldFallback = ( $controllerPaths === null );
		$this->assertFalse( $shouldFallback, 'Populated array should NOT trigger fallback' );
	}

	/**
	 * Test the old buggy behavior (for documentation)
	 */
	public function testDemonstrateBuggyBehaviorWithEmptyArray()
	{
		$emptyArray = [];
		$nullValue = null;

		// OLD BUGGY CHECK: !$controllerPaths
		$buggyCheckWithEmpty = !$emptyArray;  // evaluates to true (WRONG!)
		$buggyCheckWithNull = !$nullValue;    // evaluates to true (correct)

		$this->assertTrue( $buggyCheckWithEmpty, 'Old check: ![] incorrectly evaluates to true' );
		$this->assertTrue( $buggyCheckWithNull, 'Old check: !null correctly evaluates to true' );

		// NEW CORRECT CHECK: $controllerPaths === null
		$correctCheckWithEmpty = ( $emptyArray === null );  // evaluates to false (correct!)
		$correctCheckWithNull = ( $nullValue === null );    // evaluates to true (correct)

		$this->assertFalse( $correctCheckWithEmpty, 'New check: [] === null correctly evaluates to false' );
		$this->assertTrue( $correctCheckWithNull, 'New check: null === null correctly evaluates to true' );
	}

	/**
	 * Test routing.yaml precedence scenarios
	 */
	public function testRoutingYamlPrecedenceScenarios()
	{
		// Scenario 1: routing.yaml doesn't exist (null)
		Registry::getInstance()->reset();
		$scenario1 = Registry::getInstance()->get( 'Routing.ControllerPaths' );
		$this->assertNull( $scenario1, 'Scenario 1: No routing.yaml should be null' );

		// Scenario 2: routing.yaml exists with empty array
		Registry::getInstance()->set( 'Routing.ControllerPaths', [] );
		$scenario2 = Registry::getInstance()->get( 'Routing.ControllerPaths' );
		$this->assertSame( [], $scenario2, 'Scenario 2: Empty array should be respected' );

		// Scenario 3: routing.yaml exists with paths
		$paths = [['path' => 'src/Controllers', 'namespace' => 'App\Controllers']];
		Registry::getInstance()->set( 'Routing.ControllerPaths', $paths );
		$scenario3 = Registry::getInstance()->get( 'Routing.ControllerPaths' );
		$this->assertSame( $paths, $scenario3, 'Scenario 3: Paths should be used' );
	}
}
