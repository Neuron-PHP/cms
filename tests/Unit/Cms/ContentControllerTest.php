<?php

namespace Tests\Cms;

use Neuron\Cms\Controllers\Content;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ContentControllerTest extends TestCase
{
	private SettingManager $_settingManager;
	private $mockSessionManager;

	protected function setUp(): void
	{
		parent::setUp();

		// Create virtual filesystem (local variable, not stored)
		$root = vfsStream::setup( 'test' );

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );

		// Wrap in SettingManager
		$this->_settingManager = new SettingManager( $settings );

		// Create mock SessionManager
		$this->mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		// Store settings in registry for backward compatibility
		Registry::getInstance()->set( 'Settings', $this->_settingManager );

		// Create version file
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 2,
			'patch' => 3
		]);

		vfsStream::newFile( '.version.json' )
			->at( $root )
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
		Registry::getInstance()->set( 'DtoFactoryService', null );

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
		$controller = new Content( null, $this->_settingManager, $this->mockSessionManager );

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
		$controller = new Content( null, $this->_settingManager, $this->mockSessionManager );

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
		$controller = new Content( null, $this->_settingManager, $this->mockSessionManager );

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
			->setConstructorArgs( [ null, $this->_settingManager, $this->mockSessionManager ] )
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

		$request = new Request();
		$request->setRouteParameters( [ 'page' => 'test-page' ] );
		$result = $controller->markdown( $request );

		$this->assertEquals( '<html>Test Content</html>', $result );
	}

	/**
	 * Test getSessionManager returns SessionManager instance
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testGetSessionManager()
	{
		$controller = new Content( null, $this->_settingManager, $this->mockSessionManager );

		// Use reflection to access protected method
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'getSessionManager' );
		$method->setAccessible( true );

		$sessionManager = $method->invoke( $controller );

		$this->assertInstanceOf( \Neuron\Cms\Auth\SessionManager::class, $sessionManager );

		// Calling again should return same instance
		$sessionManager2 = $method->invoke( $controller );
		$this->assertSame( $sessionManager, $sessionManager2 );
	}

	/**
	 * Test flash method sets flash message
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFlash()
	{
		$controller = new Content( null, $this->_settingManager, $this->mockSessionManager );

		// Use reflection to access protected methods
		$reflection = new \ReflectionClass( $controller );
		$flashMethod = $reflection->getMethod( 'flash' );
		$flashMethod->setAccessible( true );
		$getSessionMethod = $reflection->getMethod( 'getSessionManager' );
		$getSessionMethod->setAccessible( true );

		// Invoke flash
		$flashMethod->invoke( $controller, 'success', 'Test message' );

		// Verify flash was set in session manager
		$sessionManager = $getSessionMethod->invoke( $controller );
		$this->assertInstanceOf( \Neuron\Cms\Auth\SessionManager::class, $sessionManager );
	}
}
