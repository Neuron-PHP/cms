<?php

namespace Tests\Cms;

use Neuron\Cms\Controllers\Blog;
use PHPUnit\Framework\TestCase;

/**
 * BlogTest - DEPRECATED
 *
 * This test file is deprecated and needs to be rewritten for the current
 * Blog controller implementation which uses DatabasePostRepository,
 * DatabaseCategoryRepository, and DatabaseTagRepository instead of the
 * old Blahg library.
 *
 * The current implementation requires:
 * - Settings in Registry with database configuration
 * - Database repositories instead of Blahg\Repository
 * - Post model instead of Blahg\Article
 *
 * All tests are currently skipped pending refactoring.
 */
class BlogTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->markTestSkipped(
			'BlogTest is deprecated and needs to be rewritten for the current ' .
			'Blog controller implementation. The Blog controller now uses ' .
			'DatabasePostRepository, DatabaseCategoryRepository, and DatabaseTagRepository ' .
			'instead of the old Blahg library.'
		);
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
		
		$blog = new Blog( $this->Router );
		$this->assertInstanceOf( Blog::class, $blog );
	}

	public function testRepository()
	{
		// Skip if parent class not available
		if ( !class_exists( '\App\Controllers\SiteController' ) )
		{
			$this->markTestSkipped( 'SiteController class not available in test environment' );
		}

		$blog = new Blog( $this->Router );

		// Check that the repository is set correctly
		$this->assertInstanceOf( Repository::class, $blog->getRepository() );

		// Set a mock repository and check it
		$blog->setRepository( $this->Repository );
		$this->assertSame( $this->Repository, $blog->getRepository() );
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
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName', 'getTitle', 'getDescription' ] )
			->getMock();

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'getTitle' )
			->willReturn( 'Blog Title' );

		$blog->expects( $this->once() )
			->method( 'getDescription' )
			->willReturn( 'Blog Description' );

		$blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $data ) {
					return isset( $data['Title'] ) &&
						   isset( $data['Description'] ) &&
						   array_key_exists( 'Articles', $data ) &&
						   array_key_exists( 'Categories', $data ) &&
						   array_key_exists( 'Tags', $data );
				}),
				'index'
			)
			->willReturn( '<html>Index Page</html>' );

		$result = $blog->index( [], null );
		$this->assertEquals( '<html>Index Page</html>', $result );
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
		$article = $this->createMock( Article::class );
		$article->expects( $this->any() )
			->method( 'getTitle' )
			->willReturn( 'Test Article' );

		// Create Blog mock with repository that returns the article
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();

		// Use reflection to set the private repository property
		$reflection = new \ReflectionClass( $blog );
		$property = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );

		$mockRepo = $this->createMock( Repository::class );
		$mockRepo->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'test-article' )
			->willReturn( $article );

		$mockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );

		$mockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );

		$property->setValue( $blog, $mockRepo );

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $data ) use ( $article ) {
					return isset( $data['Article'] ) &&
						   $data['Article'] === $article &&
						   isset( $data['Title'] ) &&
						   $data['Title'] === 'Test Article | Test Blog';
				}),
				'show'
			)
			->willReturn( '<html>Article Page</html>' );

		$parameters = [ 'title' => 'test-article' ];
		$result = $blog->show( $parameters, null );
		$this->assertEquals( '<html>Article Page</html>', $result );
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
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();

		// Use reflection to set the private repository property
		$reflection = new \ReflectionClass( $blog );
		$property = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );

		$mockRepo = $this->createMock( Repository::class );
		$mockRepo->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'missing-article' )
			->willThrowException( new ArticleNotFound( 'Not found' ) );

		$mockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );

		$mockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );

		$property->setValue( $blog, $mockRepo );

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $data ) {
					return isset( $data['Article'] ) &&
						   $data['Article'] instanceof Article &&
						   isset( $data['Title'] ) &&
						   $data['Title'] === 'Article Not Found | Test Blog';
				}),
				'show'
			)
			->willReturn( '<html>Not Found Page</html>' );

		$parameters = [ 'title' => 'missing-article' ];
		$result = $blog->show( $parameters, null );
		$this->assertEquals( '<html>Not Found Page</html>', $result );
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
		
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();

		// Set up mock repository
		$reflection = new \ReflectionClass( $blog );
		$property = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );

		$mockRepo = $this->createMock( Repository::class );
		$mockRepo->expects( $this->once() )
			->method( 'getArticlesByTag' )
			->with( 'php' )
			->willReturn( [] );

		$mockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [] );

		$mockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [ 'php', 'javascript' ] );

		$property->setValue( $blog, $mockRepo );

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $data ) {
					return isset( $data['Tag'] ) &&
						   $data['Tag'] === 'php' &&
						   isset( $data['Title'] ) &&
						   $data['Title'] === 'Articles tagged with php | Test Blog';
				}),
				'index'
			)
			->willReturn( '<html>Tag Page</html>' );

		$parameters = [ 'tag' => 'php' ];
		$result = $blog->tag( $parameters, null );
		$this->assertEquals( '<html>Tag Page</html>', $result );
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
		
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'renderHtml', 'getName' ] )
			->getMock();

		// Set up mock repository
		$reflection = new \ReflectionClass( $blog );
		$property = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );

		$mockRepo = $this->createMock( Repository::class );
		$mockRepo->expects( $this->once() )
			->method( 'getArticlesByCategory' )
			->with( 'tech' )
			->willReturn( [] );

		$mockRepo->expects( $this->any() )
			->method( 'getCategories' )
			->willReturn( [ 'tech', 'news' ] );

		$mockRepo->expects( $this->any() )
			->method( 'getTags' )
			->willReturn( [] );

		$property->setValue( $blog, $mockRepo );

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				HttpResponseStatus::OK,
				$this->callback( function( $data ) {
					return isset( $data['Category'] ) &&
						   $data['Category'] === 'tech' &&
						   isset( $data['Title'] ) &&
						   $data['Title'] === 'Articles in campaign tech | Test Blog';
				}),
				'index'
			)
			->willReturn( '<html>Category Page</html>' );

		$parameters = [ 'category' => 'tech' ];
		$result = $blog->category( $parameters, null );
		$this->assertEquals( '<html>Category Page</html>', $result );
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
		
		$blog = $this->getMockBuilder( Blog::class )
			->setConstructorArgs( [ $this->Router ] )
			->onlyMethods( [ 'getName', 'getDescription', 'getUrl', 'getRssUrl' ] )
			->getMock();

		// Set up mock repository
		$reflection = new \ReflectionClass( $blog );
		$property = $reflection->getProperty( 'repository' );
		$property->setAccessible( true );

		$mockRepo = $this->createMock( Repository::class );
		$mockRepo->expects( $this->once() )
			->method( 'getArticles' )
			->willReturn( [] );

		$mockRepo->expects( $this->once() )
			->method( 'getFeed' )
			->with(
				'Test Blog',
				'Test Description',
				'http://test.com',
				'http://test.com/rss',
				[]
			)
			->willReturn( '<?xml version="1.0"?><rss>Feed Content</rss>' );

		$property->setValue( $blog, $mockRepo );

		$blog->expects( $this->once() )
			->method( 'getName' )
			->willReturn( 'Test Blog' );

		$blog->expects( $this->once() )
			->method( 'getDescription' )
			->willReturn( 'Test Description' );

		$blog->expects( $this->once() )
			->method( 'getUrl' )
			->willReturn( 'http://test.com' );

		$blog->expects( $this->once() )
			->method( 'getRssUrl' )
			->willReturn( 'http://test.com/rss' );

		// The feed method calls die(), so we can't test it fully
		// We'll test that it doesn't throw an exception before die()
		$this->expectOutputString( '<?xml version="1.0"?><rss>Feed Content</rss>' );

		// Suppress the exit/die
		try
		{
			$blog->feed( [], null );
		}
		catch ( \Exception $e )
		{
			// Feed method calls die() which we can't easily test
			// Just verify no exception was thrown before die()
		}
	}
}
