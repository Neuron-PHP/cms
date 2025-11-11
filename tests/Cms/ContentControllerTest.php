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
	private $root;
	private $router;
	private $settings;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// Create virtual filesystem
		$this->root = vfsStream::setup( 'test' );

		// Create mock router
		$this->router = $this->createMock( Router::class );

		// Create mock settings
		$this->settings = new Memory();
		$this->settings->set( 'site', 'name', 'Test Site' );
		$this->settings->set( 'site', 'title', 'Test Title' );
		$this->settings->set( 'site', 'description', 'Test Description' );
		$this->settings->set( 'site', 'url', 'http://test.com' );

		// Store settings in registry
		Registry::getInstance()->set( 'Settings', $this->settings );

		// Create version file
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 2,
			'patch' => 3
		]);

		vfsStream::newFile( '.version.json' )
			->at( $this->root )
			->setContent( $versionContent );

		// Create a real version file in parent directory for the controller
		// ContentController loads from "../.version.json"
		$parentDir = dirname( getcwd() );
		if ( !file_exists( $parentDir . '/.version.json' ) )
		{
			file_put_contents( $parentDir . '/.version.json', $versionContent );
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
		$parentDir = dirname( getcwd() );
		@unlink( $parentDir . '/.version.json' );
		
		parent::tearDown();
	}
	
	/**
	 * Test ContentController constructor
	 */
	public function testConstructor()
	{
		$controller = new Content();

		// Check that properties were set from settings
		$this->assertEquals( 'Test Site', $controller->getName() );
		$this->assertEquals( 'Test Title', $controller->getTitle() );
		$this->assertEquals( 'Test Description', $controller->getDescription() );
		$this->assertEquals( 'http://test.com', $controller->getUrl() );
		$this->assertEquals( 'http://test.com/blog/rss', $controller->getRssUrl() );

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
		$controller = new Content();

		// Test Name
		$controller->setName( 'New Name' );
		$this->assertEquals( 'New Name', $controller->getName() );

		// Test Title
		$controller->setTitle( 'New Title' );
		$this->assertEquals( 'New Title', $controller->getTitle() );

		// Test Description
		$controller->setDescription( 'New Description' );
		$this->assertEquals( 'New Description', $controller->getDescription() );

		// Test URL
		$controller->setUrl( 'http://newurl.com' );
		$this->assertEquals( 'http://newurl.com', $controller->getUrl() );

		// Test RSS URL
		$controller->setRssUrl( 'http://newurl.com/rss' );
		$this->assertEquals( 'http://newurl.com/rss', $controller->getRssUrl() );
	}
	
	/**
	 * Test method chaining
	 */
	public function testMethodChaining()
	{
		$controller = new Content();

		$result = $controller
			->setName( 'Chained Name' )
			->setTitle( 'Chained Title' )
			->setDescription( 'Chained Description' )
			->setUrl( 'http://chained.com' )
			->setRssUrl( 'http://chained.com/rss' );

		// Should return the controller itself for chaining
		$this->assertInstanceOf( Content::class, $result );

		// Values should be set
		$this->assertEquals( 'Chained Name', $controller->getName() );
		$this->assertEquals( 'Chained Title', $controller->getTitle() );
		$this->assertEquals( 'Chained Description', $controller->getDescription() );
		$this->assertEquals( 'http://chained.com', $controller->getUrl() );
		$this->assertEquals( 'http://chained.com/rss', $controller->getRssUrl() );
	}
	
	/**
	 * Test markdown method
	 * Note: This will throw an exception because the view files don't exist
	 * in our test environment, but we can test the method is callable
	 */
	public function testMarkdownMethod()
	{
		$controller = $this->getMockBuilder( Content::class )
			->onlyMethods( [ 'renderMarkdown' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderMarkdown' )
			->with(
				HttpResponseStatus::OK,
				$this->isType( 'array' ),
				'test-page'
			)
			->willReturn( '<html>Test Content</html>' );

		$parameters = [ 'page' => 'test-page' ];
		$result = $controller->markdown( $parameters );

		$this->assertEquals( '<html>Test Content</html>', $result );
	}
}
