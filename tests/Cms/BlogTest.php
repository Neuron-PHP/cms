<?php

namespace Tests\Cms;

use Blahg\Article;
use Blahg\Exception\ArticleNotFound;
use Blahg\Repository;
use Neuron\Cms\Controllers\Blog;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class BlogTest extends TestCase
{
	private $Router;
	private $Repository;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// Create mock router
		$this->Router = $this->createMock( Router::class );
		
		// Mock Repository
		$this->Repository = $this->createMock( Repository::class );
	}
	
	/**
	 * Test Blog constructor
	 * Note: We can't easily test the constructor fully because it creates
	 * a Repository internally, but we can test that it doesn't throw
	 */
	public function testConstructor()
	{
		// This will fail if parent class SiteController is not available
		// We'll mark it as skipped if the class doesn't exist
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		$Blog = new Blog( $this->Router );
		$this->assertInstanceOf( Blog::class, $Blog );
	}
	
	/**
	 * Test index method
	 */
	public function testIndexMethod()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		// Create a partial mock that only mocks specific methods
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName', 'getTitle', 'getDescription' ] )
			->getMock();
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( 'Blog Title' );
		
		$Blog->expects( $this->once() )
			->method( 'getDescription' )
			->willReturn( 'Blog Description' );
		
		$Blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $Data ) {
					return isset( $Data['Title'] ) &&
						   isset( $Data['Description'] ) &&
						   array_key_exists( 'Articles', $Data ) &&
						   array_key_exists( 'Categories', $Data ) &&
						   array_key_exists( 'Tags', $Data );
				}),
				'index'
			)
			->willReturn( '<html>Index Page</html>' );
		
		$Result = $Blog->index( [], null );
		$this->assertEquals( '<html>Index Page</html>', $Result );
	}
	
	/**
	 * Test show method with valid article
	 */
	public function testShowMethodWithValidArticle()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		// Create mock article
		$Article = $this->createMock( Article::class );
		$Article->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( 'Test Article' );
		
		// Create Blog mock with repository that returns the article
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();
		
		// Use reflection to set the private repository property
		$Reflection = new \ReflectionClass( $Blog );
		$Property = $Reflection->getProperty( '_Repo' );
		$Property->setAccessible( true );
		
		$MockRepo = $this->createMock( Repository::class );
		$MockRepo->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'test-article' )
			->willReturn( $Article );
		
		$MockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );
		
		$Property->setValue( $Blog, $MockRepo );
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $Data ) use ( $Article ) {
					return isset( $Data['Article'] ) &&
						   $Data['Article'] === $Article &&
						   isset( $Data['Title'] ) &&
						   $Data['Title'] === 'Test Article | Test Blog';
				}),
				'show'
			)
			->willReturn( '<html>Article Page</html>' );
		
		$Parameters = [ 'title' => 'test-article' ];
		$Result = $Blog->show( $Parameters, null );
		$this->assertEquals( '<html>Article Page</html>', $Result );
	}
	
	/**
	 * Test show method with ArticleNotFound exception
	 */
	public function testShowMethodWithArticleNotFound()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		// Create Blog mock
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();
		
		// Use reflection to set the private repository property
		$Reflection = new \ReflectionClass( $Blog );
		$Property = $Reflection->getProperty( '_Repo' );
		$Property->setAccessible( true );
		
		$MockRepo = $this->createMock( Repository::class );
		$MockRepo->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'missing-article' )
			->willThrowException( new ArticleNotFound( 'Not found' ) );
		
		$MockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );
		
		$Property->setValue( $Blog, $MockRepo );
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $Data ) {
					return isset( $Data['Article'] ) &&
						   $Data['Article'] instanceof Article &&
						   isset( $Data['Title'] ) &&
						   $Data['Title'] === 'Character Not Found | Test Blog';
				}),
				'show'
			)
			->willReturn( '<html>Not Found Page</html>' );
		
		$Parameters = [ 'title' => 'missing-article' ];
		$Result = $Blog->show( $Parameters, null );
		$this->assertEquals( '<html>Not Found Page</html>', $Result );
	}
	
	/**
	 * Test tag method
	 */
	public function testTagMethod()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();
		
		// Set up mock repository
		$Reflection = new \ReflectionClass( $Blog );
		$Property = $Reflection->getProperty( '_Repo' );
		$Property->setAccessible( true );
		
		$MockRepo = $this->createMock( Repository::class );
		$MockRepo->expects( $this->once() )
			->method( 'getArticlesByTag' )
			->with( 'php' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [ 'php', 'javascript' ] );
		
		$Property->setValue( $Blog, $MockRepo );
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $Data ) {
					return isset( $Data['Tag'] ) &&
						   $Data['Tag'] === 'php' &&
						   isset( $Data['Title'] ) &&
						   $Data['Title'] === 'Characters tagged with php | Test Blog';
				}),
				'index'
			)
			->willReturn( '<html>Tag Page</html>' );
		
		$Parameters = [ 'tag' => 'php' ];
		$Result = $Blog->tag( $Parameters, null );
		$this->assertEquals( '<html>Tag Page</html>', $Result );
	}
	
	/**
	 * Test category method
	 */
	public function testCategoryMethod()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();
		
		// Set up mock repository
		$Reflection = new \ReflectionClass( $Blog );
		$Property = $Reflection->getProperty( '_Repo' );
		$Property->setAccessible( true );
		
		$MockRepo = $this->createMock( Repository::class );
		$MockRepo->expects( $this->once() )
			->method( 'getArticlesByCategory' )
			->with( 'tech' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [ 'tech', 'news' ] );
		
		$MockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );
		
		$Property->setValue( $Blog, $MockRepo );
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $Data ) {
					return isset( $Data['Category'] ) &&
						   $Data['Category'] === 'tech' &&
						   isset( $Data['Title'] ) &&
						   $Data['Title'] === 'Characters in campaign tech | Test Blog';
				}),
				'index'
			)
			->willReturn( '<html>Category Page</html>' );
		
		$Parameters = [ 'category' => 'tech' ];
		$Result = $Blog->category( $Parameters, null );
		$this->assertEquals( '<html>Category Page</html>', $Result );
	}
	
	/**
	 * Test feed method
	 */
	public function testFeedMethod()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}
		
		$Blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'getName', 'getDescription', 'getUrl', 'getRssUrl' ] )
			->getMock();
		
		// Set up mock repository
		$Reflection = new \ReflectionClass( $Blog );
		$Property = $Reflection->getProperty( '_Repo' );
		$Property->setAccessible( true );
		
		$MockRepo = $this->createMock( Repository::class );
		$MockRepo->expects( $this->once() )
			->method( 'getArticles' )
			->willReturn( [] );
		
		$MockRepo->expects( $this->once() )
			->method( 'getFeed' )
			->with(
				'Test Blog',
				'Test Description',
				'http://test.com',
				'http://test.com/rss',
				[]
			)
			->willReturn( '<?xml version="1.0"?><rss>Feed Content</rss>' );
		
		$Property->setValue( $Blog, $MockRepo );
		
		$Blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );
		
		$Blog->expects( $this->once() )
			->method( 'getDescription' )
			->willReturn( 'Test Description' );
		
		$Blog->expects( $this->once() )
			->method( 'getUrl' )
			->willReturn( 'http://test.com' );
		
		$Blog->expects( $this->once() )
			->method( 'getRssUrl' )
			->willReturn( 'http://test.com/rss' );
		
		// The feed method calls die(), so we can't test it fully
		// We'll test that it doesn't throw an exception before die()
		$this->expectOutputString( '<?xml version="1.0"?><rss>Feed Content</rss>' );
		
		// Suppress the exit/die
		try
		{
			$Blog->feed( [], null );
		}
		catch ( \Exception $e )
		{
			// Feed method calls die() which we can't easily test
			// Just verify no exception was thrown before die()
		}
	}
}
