<?php

namespace Tests\Cms;

use Neuron\Cms\Controllers\Content;
use Neuron\Data\Setting\Source\Memory;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ContentControllerTest extends TestCase
{
	private $Root;
	private $Router;
	private $Settings;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// Create virtual filesystem
		$this->Root = vfsStream::setup( 'test' );
		
		// Create mock router
		$this->Router = $this->createMock( Router::class );
		
		// Create mock settings
		$this->Settings = new Memory();
		$this->Settings->set( 'site', 'name', 'Test Site' );
		$this->Settings->set( 'site', 'title', 'Test Title' );
		$this->Settings->set( 'site', 'description', 'Test Description' );
		$this->Settings->set( 'site', 'url', 'http://test.com' );
		
		// Store settings in registry
		Registry::getInstance()->set( 'Settings', $this->Settings );
		
		// Create version file
		$VersionContent = json_encode([
			'major' => 1,
			'minor' => 2,
			'patch' => 3
		]);
		
		vfsStream::newFile( '.version.json' )
			->at( $this->Root )
			->setContent( $VersionContent );
		
		// Create a real version file in parent directory for the controller
		// ContentController loads from "../.version.json"
		$ParentDir = dirname( getcwd() );
		if ( !file_exists( $ParentDir . '/.version.json' ) )
		{
			file_put_contents( $ParentDir . '/.version.json', $VersionContent );
		}
	}
	
	protected function tearDown(): void
	{
		// Clear registry
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		
		// Clean up temp version file
		$ParentDir = dirname( getcwd() );
		@unlink( $ParentDir . '/.version.json' );
		
		parent::tearDown();
	}
	
	/**
	 * Test ContentController constructor
	 */
	public function testConstructor()
	{
		$Controller = new Content();
		
		// Check that properties were set from settings
		$this->assertEquals( 'Test Site', $Controller->getName() );
		$this->assertEquals( 'Test Title', $Controller->getTitle() );
		$this->assertEquals( 'Test Description', $Controller->getDescription() );
		$this->assertEquals( 'http://test.com', $Controller->getUrl() );
		$this->assertEquals( 'http://test.com/blog/rss', $Controller->getRssUrl() );
		
		// Check registry values were set
		$this->assertEquals( 'v1.2.3', Registry::getInstance()->get( 'version' ) );
		$this->assertEquals( 'Test Site', Registry::getInstance()->get( 'name' ) );
		$this->assertEquals( 'http://test.com/blog/rss', Registry::getInstance()->get( 'rss_url' ) );
	}
	
	/**
	 * Test setters and getters
	 */
	public function testSettersAndGetters()
	{
		$Controller = new Content();
		
		// Test Name
		$Controller->setName( 'New Name' );
		$this->assertEquals( 'New Name', $Controller->getName() );
		
		// Test Title
		$Controller->setTitle( 'New Title' );
		$this->assertEquals( 'New Title', $Controller->getTitle() );
		
		// Test Description
		$Controller->setDescription( 'New Description' );
		$this->assertEquals( 'New Description', $Controller->getDescription() );
		
		// Test URL
		$Controller->setUrl( 'http://newurl.com' );
		$this->assertEquals( 'http://newurl.com', $Controller->getUrl() );
		
		// Test RSS URL
		$Controller->setRssUrl( 'http://newurl.com/rss' );
		$this->assertEquals( 'http://newurl.com/rss', $Controller->getRssUrl() );
	}
	
	/**
	 * Test method chaining
	 */
	public function testMethodChaining()
	{
		$Controller = new Content();
		
		$Result = $Controller
			->setName( 'Chained Name' )
			->setTitle( 'Chained Title' )
			->setDescription( 'Chained Description' )
			->setUrl( 'http://chained.com' )
			->setRssUrl( 'http://chained.com/rss' );
		
		// Should return the controller itself for chaining
		$this->assertInstanceOf( Content::class, $Result );
		
		// Values should be set
		$this->assertEquals( 'Chained Name', $Controller->getName() );
		$this->assertEquals( 'Chained Title', $Controller->getTitle() );
		$this->assertEquals( 'Chained Description', $Controller->getDescription() );
		$this->assertEquals( 'http://chained.com', $Controller->getUrl() );
		$this->assertEquals( 'http://chained.com/rss', $Controller->getRssUrl() );
	}
	
	/**
	 * Test markdown method
	 * Note: This will throw an exception because the view files don't exist
	 * in our test environment, but we can test the method is callable
	 */
	public function testMarkdownMethod()
	{
		$Controller = $this->getMockBuilder( Content::class )
			->onlyMethods( [ 'renderMarkdown' ] )
			->getMock();
		
		$Controller->expects( $this->once() )
			->method( 'renderMarkdown' )
			->with(
				HttpResponseStatus::OK,
				$this->isType( 'array' ),
				'test-page'
			)
			->willReturn( '<html>Test Content</html>' );
		
		$Parameters = [ 'page' => 'test-page' ];
		$Result = $Controller->markdown( $Parameters );
		
		$this->assertEquals( '<html>Test Content</html>', $Result );
	}
}
