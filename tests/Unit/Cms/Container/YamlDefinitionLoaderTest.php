<?php

namespace Tests\Cms\Container;

use Neuron\Cms\Container\YamlDefinitionLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests for YamlDefinitionLoader
 *
 * @package Tests\Cms\Container
 */
class YamlDefinitionLoaderTest extends TestCase
{
	private string $_tempDir;

	protected function setUp(): void
	{
		parent::setUp();

		// Create temporary directory for test YAML files
		$this->_tempDir = sys_get_temp_dir() . '/yaml_loader_test_' . uniqid();
		mkdir( $this->_tempDir );
	}

	protected function tearDown(): void
	{
		// Clean up temporary files
		if( is_dir( $this->_tempDir ) )
		{
			$files = glob( $this->_tempDir . '/*' );
			foreach( $files as $file )
			{
				if( is_file( $file ) )
				{
					unlink( $file );
				}
			}
			rmdir( $this->_tempDir );
		}

		parent::tearDown();
	}

	/**
	 * Test loading basic service definitions
	 */
	public function testLoadBasicServiceDefinitions(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: autowire
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'TestService', $definitions );
	}

	/**
	 * Test autowire definition type
	 */
	public function testAutowireDefinition(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: autowire
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'TestService', $definitions );
		// PHP-DI autowire definitions are callable
		$this->assertIsObject( $definitions['TestService'] );
	}

	/**
	 * Test create definition with constructor parameters
	 */
	public function testCreateDefinitionWithConstructor(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: create
    constructor:
      - '@DependencyService'
      - 'string_value'
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'TestService', $definitions );
	}

	/**
	 * Test alias definition
	 */
	public function testAliasDefinition(): void
	{
		$yaml = <<<YAML
services:
  ITestService:
    type: alias
    target: TestServiceImplementation
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'ITestService', $definitions );
	}

	/**
	 * Test shorthand alias syntax
	 */
	public function testShorthandAliasSyntax(): void
	{
		$yaml = <<<YAML
services:
  ITestService: TestServiceImplementation
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'ITestService', $definitions );
	}

	/**
	 * Test factory definition
	 */
	public function testFactoryDefinition(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: factory
    factory_class: TestFactory
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'TestService', $definitions );
	}

	/**
	 * Test environment-specific configuration override
	 */
	public function testEnvironmentSpecificOverride(): void
	{
		// Base configuration
		$baseYaml = <<<YAML
services:
  TestService:
    type: autowire
  ProductionService:
    type: autowire
YAML;

		// Testing environment override
		$testingYaml = <<<YAML
services:
  TestService:
    type: alias
    target: MockTestService
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $baseYaml );
		file_put_contents( $this->_tempDir . '/services.testing.yaml', $testingYaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir, 'testing' );
		$definitions = $loader->load();

		// Both base and override services should be present
		$this->assertArrayHasKey( 'TestService', $definitions );
		$this->assertArrayHasKey( 'ProductionService', $definitions );
	}

	/**
	 * Test environment override without base file fails
	 */
	public function testEnvironmentOverrideWithoutBaseFileFails(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Service configuration file not found' );

		$loader = new YamlDefinitionLoader( $this->_tempDir, 'testing' );
		$loader->load();
	}

	/**
	 * Test invalid YAML syntax throws exception
	 */
	public function testInvalidYamlSyntaxThrowsException(): void
	{
		$invalidYaml = <<<YAML
services:
  TestService
    type: autowire
    missing colon
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $invalidYaml );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Failed to parse service configuration' );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$loader->load();
	}

	/**
	 * Test unknown definition type throws exception
	 */
	public function testUnknownDefinitionTypeThrowsException(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: unknown_type
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( "Unknown service definition type 'unknown_type'" );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$loader->load();
	}

	/**
	 * Test parameter reference resolution
	 */
	public function testParameterReferenceResolution(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: create
    constructor:
      - '@DependencyService'
      - 'plain_string'
      - 42
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertArrayHasKey( 'TestService', $definitions );
	}

	/**
	 * Test value definition type
	 */
	public function testValueDefinition(): void
	{
		$yaml = <<<YAML
services:
  app.version:
    type: value
    value: "1.0.0"
  app.debug:
    type: value
    value: false
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertEquals( '1.0.0', $definitions['app.version'] );
		$this->assertFalse( $definitions['app.debug'] );
	}

	/**
	 * Test instance type is skipped (handled at runtime)
	 */
	public function testInstanceTypeIsSkipped(): void
	{
		$yaml = <<<YAML
services:
  RuntimeService:
    type: instance
    source: registry
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		// Instance types are not added to definitions (set at runtime)
		$this->assertArrayNotHasKey( 'RuntimeService', $definitions );
	}

	/**
	 * Test factory without factory_class throws exception
	 */
	public function testFactoryWithoutFactoryClassThrowsException(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: factory
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( "Factory definition for 'TestService' must specify factory_class" );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$loader->load();
	}

	/**
	 * Test alias without target throws exception
	 */
	public function testAliasWithoutTargetThrowsException(): void
	{
		$yaml = <<<YAML
services:
  ITestService:
    type: alias
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( "Alias definition must specify 'target'" );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$loader->load();
	}

	/**
	 * Test getDefinitions returns loaded definitions
	 */
	public function testGetDefinitions(): void
	{
		$yaml = <<<YAML
services:
  TestService:
    type: autowire
YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$loader->load();

		$definitions = $loader->getDefinitions();
		$this->assertArrayHasKey( 'TestService', $definitions );
	}

	/**
	 * Test empty YAML file returns empty array (not crash)
	 *
	 * Symfony YAML parseFile() returns null for empty files.
	 * This test ensures we handle that gracefully.
	 */
	public function testEmptyYamlFileReturnsEmptyArray(): void
	{
		// Create empty file
		file_put_contents( $this->_tempDir . '/services.yaml', '' );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertIsArray( $definitions );
		$this->assertEmpty( $definitions );
	}

	/**
	 * Test YAML file with only comments returns empty array
	 */
	public function testYamlFileWithOnlyCommentsReturnsEmptyArray(): void
	{
		$yaml = <<<YAML
# This is just a comment
# Another comment

YAML;

		file_put_contents( $this->_tempDir . '/services.yaml', $yaml );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertIsArray( $definitions );
		$this->assertEmpty( $definitions );
	}

	/**
	 * Test YAML file with only whitespace returns empty array
	 */
	public function testYamlFileWithOnlyWhitespaceReturnsEmptyArray(): void
	{
		// Only newlines and spaces (tabs can cause parse errors in YAML)
		file_put_contents( $this->_tempDir . '/services.yaml', "   \n\n   \n" );

		$loader = new YamlDefinitionLoader( $this->_tempDir );
		$definitions = $loader->load();

		$this->assertIsArray( $definitions );
		$this->assertEmpty( $definitions );
	}

	/**
	 * Test environment override file can be empty without crashing
	 */
	public function testEmptyEnvironmentOverrideFileWorks(): void
	{
		// Base file with services
		$baseYaml = <<<YAML
services:
  TestService:
    type: autowire
YAML;

		// Empty environment override file
		file_put_contents( $this->_tempDir . '/services.yaml', $baseYaml );
		file_put_contents( $this->_tempDir . '/services.testing.yaml', '' );

		$loader = new YamlDefinitionLoader( $this->_tempDir, 'testing' );
		$definitions = $loader->load();

		// Should still have base service
		$this->assertArrayHasKey( 'TestService', $definitions );
	}
}
