<?php

namespace Tests;

use Neuron\Mvc\Application;
use Neuron\Patterns\Registry;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

// Import the namespaced function
use function Neuron\Cms\Boot;

class BootstrapTest extends TestCase
{
	private $root;
	private $originalEnv;

	protected function setUp(): void
	{
		parent::setUp();

		// Create virtual filesystem
		$this->root = vfsStream::setup( 'test' );

		// Store original environment
		$this->originalEnv = $_ENV ?? [];

		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );
	}
	
	protected function tearDown(): void
	{
		// Restore original environment
		$_ENV = $this->originalEnv;
		
		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );
		
		parent::tearDown();
	}
	
	/**
	 * Test Boot function with valid config file
	 */
	public function testBootWithValidConfig()
	{
		// Create neuron.yaml
		$configContent = <<<YAML
system:
  base_path: /app
  environment: test

database:
  host: localhost
  port: 3306

site:
  name: Test Site
  title: Test Title
  description: Test Description
  url: http://test.com
YAML;

		vfsStream::newFile( 'neuron.yaml' )
			->at( $this->root )
			->setContent( $configContent );

		// Create version.json
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 0,
			'patch' => 0
		]);

		vfsStream::newFile( '.version.json' )
			->at( $this->root )
			->setContent( $versionContent );

		// Mock base path
		$basePath = vfsStream::url( 'test' );

		// Update config to use virtual filesystem path
		$configContent = str_replace( '/app', $basePath, $configContent );
		$this->root->getChild( 'neuron.yaml' )->setContent( $configContent );

		// Boot the application
		$app = Boot( $basePath );

		// Assertions
		$this->assertInstanceOf( Application::class, $app );
		$this->assertEquals( '1.0.0', $app->getVersion() );
	}
	
	/**
	 * Test Boot function with missing config file (falls back to environment)
	 */
	public function testBootWithMissingConfig()
	{
		// Set environment variable
		$basePath = vfsStream::url( 'test' );
		putenv( "SYSTEM_BASE_PATH=$basePath" );

		// Create version.json
		$versionContent = json_encode([
			'major' => 2,
			'minor' => 1,
			'patch' => 0
		]);

		vfsStream::newFile( '.version.json' )
			->at( $this->root )
			->setContent( $versionContent );

		// Boot with non-existent config path - should use environment variable
		$app = Boot( vfsStream::url( 'test/nonexistent' ) );

		// Should successfully create application with environment path
		$this->assertInstanceOf( Application::class, $app );
		$this->assertEquals( '2.1.0', $app->getVersion() );
	}
	
	/**
	 * Test Boot function defaults to current directory when no env var
	 */
	public function testBootWithDefaultBasePath()
	{
		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );

		// Create version.json
		$versionContent = json_encode([
			'major' => 3,
			'minor' => 0,
			'patch' => 1
		]);

		// Create a temporary directory for this test
		$tempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir( $tempDir );
		$tempVersionFile = $tempDir . '/.version.json';
		file_put_contents( $tempVersionFile, $versionContent );

		// Save original CWD and change to temp directory
		$originalCwd = getcwd();
		chdir( $tempDir );

		try
		{
			// Boot with non-existent config path - will use current directory
			$app = Boot( vfsStream::url( 'test/nonexistent' ) );

			// Should successfully create application
			$this->assertInstanceOf( Application::class, $app );
			$this->assertEquals( '3.0.1', $app->getVersion() );
		}
		finally
		{
			// Restore original directory
			chdir( $originalCwd );
			// Clean up temp files
			@unlink( $tempVersionFile );
			@rmdir( $tempDir );
		}
	}
	
	/**
	 * Test that CMS Boot function properly delegates to MVC Boot
	 */
	public function testBootDelegatesToMvc()
	{
		// The CMS Boot function should call the MVC Boot function
		// Let's test with a valid config in examples directory
		$app = Boot( 'examples/config' );
		$this->assertInstanceOf( Application::class, $app );
	}

	/**
	 * Test that CMS Boot registers bubble exceptions in Registry
	 */
	public function testBootRegistersBubbleExceptions()
	{
		// Clear registry before test
		Registry::getInstance()->set( 'BubbleExceptions', null );

		// Boot the CMS application
		$app = Boot( 'examples/config' );

		// Verify bubble exceptions are registered
		$bubbleExceptions = Registry::getInstance()->get( 'BubbleExceptions' );

		$this->assertIsArray( $bubbleExceptions );
		$this->assertContains( 'Neuron\\Cms\\Exceptions\\UnauthenticatedException', $bubbleExceptions );
		$this->assertContains( 'Neuron\\Cms\\Exceptions\\EmailVerificationRequiredException', $bubbleExceptions );
		$this->assertContains( 'Neuron\\Cms\\Exceptions\\CsrfValidationException', $bubbleExceptions );
	}

	/**
	 * Test that registered bubble exceptions are the correct classes
	 */
	public function testBubbleExceptionsAreValidClasses()
	{
		// Boot the CMS application
		$app = Boot( 'examples/config' );

		// Get registered exceptions
		$bubbleExceptions = Registry::getInstance()->get( 'BubbleExceptions' );

		// Verify each exception class exists
		foreach( $bubbleExceptions as $exceptionClass )
		{
			$this->assertTrue(
				class_exists( $exceptionClass ),
				"Bubble exception class does not exist: $exceptionClass"
			);
		}
	}
}