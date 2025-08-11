<?php

namespace Tests\Cms;

use Blahg\Article;
use Blahg\Exception\ArticleMissingBody;
use Blahg\Exception\ArticleNotFound;
use Blahg\Repository;
use Neuron\Cms\Blog;
use Neuron\Data\Filter\Get;
use Neuron\Data\Setting\Source\Memory;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class BlogControllerTest extends TestCase
{
	private Blog $Blog;
	private $MockRouter;
	private $MockRepository;
	private $MockGet;
	private $OriginalRegistry;
	private $OriginalCwd;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// Store original working directory
		$this->OriginalCwd = getcwd();
		// Change to test directory so version file can be found
		chdir( __DIR__ . '/..' );
		
		// Store original registry values
		$this->OriginalRegistry = [
			'Settings' => Registry::getInstance()->get( 'Settings' ),
			'Base.Path' => Registry::getInstance()->get( 'Base.Path' ),
			'Views.Path' => Registry::getInstance()->get( 'Views.Path' )
		];
		
		// Set up mock settings
		$Settings = new Memory();
		$Settings->set( 'site', 'name', 'Test Blog' );
		$Settings->set( 'site', 'title', 'Test Blog Title' );
		$Settings->set( 'site', 'description', 'Test Blog Description' );
		$Settings->set( 'site', 'url', 'http://test.com' );
		Registry::getInstance()->set( 'Settings', $Settings );
		
		// Set paths for views
		Registry::getInstance()->set( 'Base.Path', __DIR__ . '/..' );
		Registry::getInstance()->set( 'Views.Path', __DIR__ . '/../resources/views' );
		
		// Create mock router
		$this->MockRouter = $this->createMock( Router::class );
		
		// Create mock repository
		$this->MockRepository = $this->createMock( Repository::class );
		
		// Create mock Get filter
		$this->MockGet = $this->createMock( Get::class );
	}
	
	protected function tearDown(): void
	{
		// Restore original registry values
		foreach( $this->OriginalRegistry as $Key => $Value )
		{
			Registry::getInstance()->set( $Key, $Value );
		}
		// Restore original working directory
		chdir( $this->OriginalCwd );
		parent::tearDown();
	}
	
	/**
	 * Create a Blog instance with mocked dependencies
	 */
	private function createBlogWithMockedRepository(): Blog
	{
		// We'll use reflection to inject the mock repository
		$Blog = new Blog( $this->MockRouter );
		
		// Use reflection to replace the private $_Repo property
		$Reflection = new \ReflectionClass( $Blog );
		$RepoProperty = $Reflection->getProperty( '_Repo' );
		$RepoProperty->setAccessible( true );
		$RepoProperty->setValue( $Blog, $this->MockRepository );
		
		return $Blog;
	}
	
	/**
	 * Create sample articles for testing
	 */
	private function createSampleArticle( string $Title = 'Test Article', string $Slug = 'test-article' ): Article
	{
		$Article = new Article();
		$Article->setTitle( $Title );
		$Article->setSlug( $Slug );
		$Article->setBody( 'This is test content.' );
		$Article->setTags( [ 'test', 'php' ] );
		$Article->setCategory( 'Testing' );
		$Article->setDatePublished( '2024-01-15' );
		$Article->setAuthor( 'Test Author' );
		
		return $Article;
	}
	
	/**
	 * Test index method returns all articles
	 */
	public function testIndexReturnsAllArticles()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		// Set up mock data
		$Articles = [
			$this->createSampleArticle( 'Article 1', 'article-1' ),
			$this->createSampleArticle( 'Article 2', 'article-2' ),
			$this->createSampleArticle( 'Article 3', 'article-3' )
		];
		
		$Categories = [ 'Testing', 'Development', 'News' ];
		$Tags = [ 'test', 'php', 'coding' ];
		
		// Configure mock repository
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticles' )
			->willReturn( $Articles );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Result = $Blog->index( [], null );
		
		// Verify the result is a string (HTML output)
		$this->assertIsString( $Result );
	}
	
	/**
	 * Test show method with valid article slug
	 */
	public function testShowWithValidSlug()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		$Article = $this->createSampleArticle( 'Test Article', 'test-article' );
		$Categories = [ 'Testing' ];
		$Tags = [ 'test', 'php' ];
		
		// Configure mock repository
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'test-article' )
			->willReturn( $Article );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Parameters = [ 'title' => 'test-article' ];
		$Result = $Blog->show( $Parameters, null );
		
		// Verify the result is a string
		$this->assertIsString( $Result );
	}
	
	/**
	 * Test show method when article is not found
	 */
	public function testShowWithArticleNotFound()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		$Categories = [ 'Testing' ];
		$Tags = [ 'test', 'php' ];
		
		// Configure mock repository to throw ArticleNotFound
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'non-existent' )
			->willThrowException( new ArticleNotFound( 'Article not found' ) );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Parameters = [ 'title' => 'non-existent' ];
		$Result = $Blog->show( $Parameters, null );
		
		// Verify the result is a string (should show error article)
		$this->assertIsString( $Result );
		// The error article would have 'Character Not Found' as title
	}
	
	/**
	 * Test show method when article is missing body
	 */
	public function testShowWithArticleMissingBody()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		$Categories = [ 'Testing' ];
		$Tags = [ 'test', 'php' ];
		
		// Configure mock repository to throw ArticleMissingBody
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticleBySlug' )
			->with( 'no-body' )
			->willThrowException( new ArticleMissingBody( 'Article body missing' ) );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Parameters = [ 'title' => 'no-body' ];
		$Result = $Blog->show( $Parameters, null );
		
		// Verify the result is a string (should show error article)
		$this->assertIsString( $Result );
		// The error article would have 'Article Body Not Found' as title
	}
	
	/**
	 * Test tag method filters articles by tag
	 */
	public function testTagFiltersArticlesByTag()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		$Tag = 'php';
		$Articles = [
			$this->createSampleArticle( 'PHP Article 1', 'php-article-1' ),
			$this->createSampleArticle( 'PHP Article 2', 'php-article-2' )
		];
		$Categories = [ 'Testing', 'Development' ];
		$Tags = [ 'test', 'php', 'coding' ];
		
		// Configure mock repository
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticlesByTag' )
			->with( $Tag )
			->willReturn( $Articles );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Parameters = [ 'tag' => $Tag ];
		$Result = $Blog->tag( $Parameters, null );
		
		// Verify the result is a string
		$this->assertIsString( $Result );
	}
	
	/**
	 * Test category method filters articles by category
	 */
	public function testCategoryFiltersArticlesByCategory()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		$Category = 'Development';
		$Articles = [
			$this->createSampleArticle( 'Dev Article 1', 'dev-article-1' ),
			$this->createSampleArticle( 'Dev Article 2', 'dev-article-2' ),
			$this->createSampleArticle( 'Dev Article 3', 'dev-article-3' )
		];
		$Categories = [ 'Testing', 'Development', 'News' ];
		$Tags = [ 'test', 'php', 'coding' ];
		
		// Configure mock repository
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticlesByCategory' )
			->with( $Category )
			->willReturn( $Articles );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getCategories' )
			->willReturn( $Categories );
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getTags' )
			->willReturn( $Tags );
		
		// Call the method
		$Parameters = [ 'category' => $Category ];
		$Result = $Blog->category( $Parameters, null );
		
		// Verify the result is a string
		$this->assertIsString( $Result );
	}
	
	/**
	 * Test feed method generates RSS feed
	 */
	public function testFeedGeneratesRssFeed()
	{
		// The feed method uses die() which we can't easily test
		// We'll mark this test as incomplete for now
		$this->markTestIncomplete(
			'Feed method uses die() which cannot be properly tested without refactoring'
		);
	}
	
	/**
	 * Test constructor sets up repository with drafts disabled by default
	 */
	public function testConstructorWithoutDrafts()
	{
		// The constructor creates a real Repository with ../blog path
		// which may not exist in test environment
		$this->markTestSkipped(
			'Constructor creates real Repository which requires ../blog directory'
		);
	}
	
	/**
	 * Test that Blog extends ContentController properly
	 */
	public function testBlogExtendsContentController()
	{
		$Blog = new Blog( $this->MockRouter );
		
		// Test inherited methods from ContentController
		$this->assertEquals( 'Test Blog', $Blog->getName() );
		$this->assertEquals( 'Test Blog Title', $Blog->getTitle() );
		$this->assertEquals( 'Test Blog Description', $Blog->getDescription() );
		$this->assertEquals( 'http://test.com', $Blog->getUrl() );
		$this->assertEquals( 'http://test.com/blog/rss', $Blog->getRssUrl() );
	}
	
	/**
	 * Test parameters are passed correctly to repository methods
	 */
	public function testParametersPassedCorrectly()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		// Test with special characters in tag
		$Tag = 'c++';
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticlesByTag' )
			->with( $Tag )
			->willReturn( [] );
		
		$this->MockRepository->method( 'getCategories' )->willReturn( [] );
		$this->MockRepository->method( 'getTags' )->willReturn( [] );
		
		$Blog->tag( [ 'tag' => $Tag ], null );
		
		// Test with special characters in category
		$Blog = $this->createBlogWithMockedRepository();
		$Category = 'Web & Mobile';
		
		$this->MockRepository->expects( $this->once() )
			->method( 'getArticlesByCategory' )
			->with( $Category )
			->willReturn( [] );
		
		$this->MockRepository->method( 'getCategories' )->willReturn( [] );
		$this->MockRepository->method( 'getTags' )->willReturn( [] );
		
		$Blog->category( [ 'category' => $Category ], null );
	}
	
	/**
	 * Test Request parameter is optional in all methods
	 */
	public function testRequestParameterIsOptional()
	{
		$Blog = $this->createBlogWithMockedRepository();
		
		// Configure mock repository with minimal responses
		$this->MockRepository->method( 'getArticles' )->willReturn( [] );
		$this->MockRepository->method( 'getCategories' )->willReturn( [] );
		$this->MockRepository->method( 'getTags' )->willReturn( [] );
		$this->MockRepository->method( 'getArticleBySlug' )->willReturn( $this->createSampleArticle() );
		$this->MockRepository->method( 'getArticlesByTag' )->willReturn( [] );
		$this->MockRepository->method( 'getArticlesByCategory' )->willReturn( [] );
		
		// Test all methods with null Request
		$this->assertIsString( $Blog->index( [], null ) );
		$this->assertIsString( $Blog->show( [ 'title' => 'test' ], null ) );
		$this->assertIsString( $Blog->tag( [ 'tag' => 'test' ], null ) );
		$this->assertIsString( $Blog->category( [ 'category' => 'test' ], null ) );
		
		// Test with actual Request object
		$MockRequest = $this->createMock( Request::class );
		$this->assertIsString( $Blog->index( [], $MockRequest ) );
		$this->assertIsString( $Blog->show( [ 'title' => 'test' ], $MockRequest ) );
		$this->assertIsString( $Blog->tag( [ 'tag' => 'test' ], $MockRequest ) );
		$this->assertIsString( $Blog->category( [ 'category' => 'test' ], $MockRequest ) );
	}
}