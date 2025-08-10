<?php

namespace Tests;

use Neuron\Mvc\Application;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

// Import the namespaced function
use function Neuron\Cms\Boot;

class BootstrapTest extends TestCase
{
	private $Root;
	private $OriginalEnv;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// Create virtual filesystem
		$this->Root = vfsStream::setup( 'test' );
		
		// Store original environment
		$this->OriginalEnv = $_ENV ?? [];
		
		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );
	}
	
	protected function tearDown(): void
	{
		// Restore original environment
		$_ENV = $this->OriginalEnv;
		
		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );
		
		parent::tearDown();
	}
	
	/**
	 * Test Boot function with valid config file
	 */
	public function testBootWithValidConfig()
	{
		// Create config.yaml
		$ConfigContent = <<<YAML
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
		
		vfsStream::newFile( 'config.yaml' )
			->at( $this->Root )
			->setContent( $ConfigContent );
		
		// Create version.json
		$VersionContent = json_encode([
			'major' => 1,
			'minor' => 0,
			'patch' => 0
		]);
		
		vfsStream::newFile( '.version.json' )
			->at( $this->Root )
			->setContent( $VersionContent );
		
		// Mock base path
		$BasePath = vfsStream::url( 'test' );
		
		// Update config to use virtual filesystem path
		$ConfigContent = str_replace( '/app', $BasePath, $ConfigContent );
		$this->Root->getChild( 'config.yaml' )->setContent( $ConfigContent );
		
		// Boot the application
		$App = Boot( $BasePath );
		
		// Assertions
		$this->assertInstanceOf( Application::class, $App );
		$this->assertEquals( '1.0.0', $App->getVersion() );
	}
	
	/**
	 * Test Boot function with missing config file (falls back to environment)
	 */
	public function testBootWithMissingConfig()
	{
		// Set environment variable
		$BasePath = vfsStream::url( 'test' );
		putenv( "SYSTEM_BASE_PATH=$BasePath" );
		
		// Create version.json
		$VersionContent = json_encode([
			'major' => 2,
			'minor' => 1,
			'patch' => 0
		]);
		
		vfsStream::newFile( '.version.json' )
			->at( $this->Root )
			->setContent( $VersionContent );
		
		// Boot with non-existent config path - should use environment variable
		$App = Boot( vfsStream::url( 'test/nonexistent' ) );
		
		// Should successfully create application with environment path
		$this->assertInstanceOf( Application::class, $App );
		$this->assertEquals( '2.1.0', $App->getVersion() );
	}
	
	/**
	 * Test Boot function defaults to current directory when no env var
	 */
	public function testBootWithDefaultBasePath()
	{
		// Clear environment variable
		putenv( 'SYSTEM_BASE_PATH' );
		
		// Create version.json
		$VersionContent = json_encode([
			'major' => 3,
			'minor' => 0,
			'patch' => 1
		]);
		
		// Create a temporary directory for this test
		$TempDir = sys_get_temp_dir() . '/neuron_cms_test_' . uniqid();
		mkdir( $TempDir );
		$TempVersionFile = $TempDir . '/.version.json';
		file_put_contents( $TempVersionFile, $VersionContent );
		
		// Save original CWD and change to temp directory
		$OriginalCwd = getcwd();
		chdir( $TempDir );
		
		try
		{
			// Boot with non-existent config path - will use current directory
			$App = Boot( vfsStream::url( 'test/nonexistent' ) );
			
			// Should successfully create application
			$this->assertInstanceOf( Application::class, $App );
			$this->assertEquals( '3.0.1', $App->getVersion() );
		}
		finally
		{
			// Restore original directory
			chdir( $OriginalCwd );
			// Clean up temp files
			@unlink( $TempVersionFile );
			@rmdir( $TempDir );
		}
	}
	
	/**
	 * Test that CMS Boot function properly delegates to MVC Boot
	 */
	public function testBootDelegatesToMvc()
	{
		// The CMS Boot function should call the MVC Boot function
		// Let's test with a valid config in examples directory
		$App = Boot( 'examples/config' );
		$this->assertInstanceOf( Application::class, $App );
	}
}