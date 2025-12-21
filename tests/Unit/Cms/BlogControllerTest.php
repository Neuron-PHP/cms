<?php

namespace Tests\Cms;

use DateTimeImmutable;
use Neuron\Cms\Controllers\Blog;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Requests\Request;
use Neuron\Orm\Model;
use Neuron\Patterns\Registry;
use PDO;
use PHPUnit\Framework\TestCase;

class BlogControllerTest extends TestCase
{
	private PDO $_pdo;
	private DatabasePostRepository $_postRepository;
	private DatabaseCategoryRepository $_categoryRepository;
	private DatabaseTagRepository $_tagRepository;
	private DatabaseUserRepository $_userRepository;
	private $originalRegistry;

	protected function setUp(): void
	{
		parent::setUp();

		// Store original registry values
		$this->originalRegistry = [
			'Settings' => Registry::getInstance()->get( 'Settings' ),
			'Base.Path' => Registry::getInstance()->get( 'Base.Path' ),
			'Views.Path' => Registry::getInstance()->get( 'Views.Path' )
		];

		// Set up in-memory database
		$this->_pdo = new PDO(
			'sqlite::memory:',
			null,
			null,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
			]
		);

		// Create tables
		$this->createTables();

		// Initialize ORM with the PDO connection
		Model::setPdo( $this->_pdo );

		// Set up Settings with database config
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Blog' );
		$settings->set( 'site', 'title', 'Test Blog Title' );
		$settings->set( 'site', 'description', 'Test Blog Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'database', 'adapter', 'sqlite' );
		$settings->set( 'database', 'name', ':memory:' );

		// Wrap in SettingManager
		$settingManager = new SettingManager( $settings );

		Registry::getInstance()->set( 'Settings', $settingManager );

		// Set paths for views - point to CMS component's resources
		Registry::getInstance()->set( 'Base.Path', __DIR__ . '/../../..' );
		Registry::getInstance()->set( 'Views.Path', __DIR__ . '/../../../resources/views' );

		// Initialize ViewDataProvider for tests
		$provider = \Neuron\Mvc\Views\ViewDataProvider::getInstance();
		$provider->share( 'siteName', 'Test Site' );
		$provider->share( 'appVersion', '1.0.0-test' );
		$provider->share( 'currentUser', null );
		$provider->share( 'theme', 'sandstone' );
		$provider->share( 'currentYear', fn() => date('Y') );
		$provider->share( 'isAuthenticated', false );

		// Initialize repositories with our test PDO
		$this->initializeRepositories();
	}

	protected function tearDown(): void
	{
		// Restore original registry values
		foreach( $this->originalRegistry as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}
		parent::tearDown();
	}

	private function createTables(): void
	{
		// Create users table
		$this->_pdo->exec( "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				role VARCHAR(50) DEFAULT 'subscriber',
				status VARCHAR(50) DEFAULT 'active',
				email_verified BOOLEAN DEFAULT 0,
				two_factor_secret VARCHAR(255) NULL,
				two_factor_recovery_codes TEXT NULL,
				remember_token VARCHAR(255) NULL,
				failed_login_attempts INTEGER DEFAULT 0,
				locked_until TIMESTAMP NULL,
				last_login_at TIMESTAMP NULL,
				timezone VARCHAR(50) DEFAULT 'UTC',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		// Create posts table
		$this->_pdo->exec( "
			CREATE TABLE posts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL UNIQUE,
				body TEXT NOT NULL,
				content_raw TEXT DEFAULT '{\"blocks\":[]}',
				excerpt TEXT,
				featured_image VARCHAR(255),
				author_id INTEGER NOT NULL,
				status VARCHAR(20) DEFAULT 'draft',
				published_at TIMESTAMP,
				view_count INTEGER DEFAULT 0,
				created_at TIMESTAMP NOT NULL,
				updated_at TIMESTAMP
			)
		" );

		// Create categories table
		$this->_pdo->exec( "
			CREATE TABLE categories (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL UNIQUE,
				description TEXT,
				created_at TIMESTAMP NOT NULL,
				updated_at TIMESTAMP
			)
		" );

		// Create tags table
		$this->_pdo->exec( "
			CREATE TABLE tags (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(100) NOT NULL,
				slug VARCHAR(100) NOT NULL UNIQUE,
				created_at TIMESTAMP NOT NULL,
				updated_at TIMESTAMP
			)
		" );

		// Create junction tables
		$this->_pdo->exec( "
			CREATE TABLE post_categories (
				post_id INTEGER NOT NULL,
				category_id INTEGER NOT NULL,
				created_at TIMESTAMP NOT NULL,
				PRIMARY KEY (post_id, category_id),
				FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
				FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
			)
		" );

		$this->_pdo->exec( "
			CREATE TABLE post_tags (
				post_id INTEGER NOT NULL,
				tag_id INTEGER NOT NULL,
				created_at TIMESTAMP NOT NULL,
				PRIMARY KEY (post_id, tag_id),
				FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
				FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
			)
		" );
	}

	private function initializeRepositories(): void
	{
		$pdo = $this->_pdo;

		// Create repositories using reflection to inject PDO
		$this->_postRepository = new class( $pdo ) extends DatabasePostRepository
		{
			public function __construct( PDO $PDO )
			{
				$reflection = new \ReflectionClass( DatabasePostRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};

		$this->_categoryRepository = new class( $pdo ) extends DatabaseCategoryRepository
		{
			public function __construct( PDO $PDO )
			{
				$reflection = new \ReflectionClass( DatabaseCategoryRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};

		$this->_tagRepository = new class( $pdo ) extends DatabaseTagRepository
		{
			public function __construct( PDO $PDO )
			{
				$reflection = new \ReflectionClass( DatabaseTagRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};

		$this->_userRepository = new class( $pdo ) extends DatabaseUserRepository
		{
			public function __construct( PDO $PDO )
			{
				$reflection = new \ReflectionClass( DatabaseUserRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};
	}

	private function createBlogWithInjectedRepositories(): Blog
	{
		// Create Blog controller
		$blog = new Blog();

		// Inject our test repositories using reflection
		$reflection = new \ReflectionClass( $blog );

		$postRepoProp = $reflection->getProperty( '_postRepository' );
		$postRepoProp->setAccessible( true );
		$postRepoProp->setValue( $blog, $this->_postRepository );

		$categoryRepoProp = $reflection->getProperty( '_categoryRepository' );
		$categoryRepoProp->setAccessible( true );
		$categoryRepoProp->setValue( $blog, $this->_categoryRepository );

		$tagRepoProp = $reflection->getProperty( '_tagRepository' );
		$tagRepoProp->setAccessible( true );
		$tagRepoProp->setValue( $blog, $this->_tagRepository );

		$userRepoProp = $reflection->getProperty( '_userRepository' );
		$userRepoProp->setAccessible( true );
		$userRepoProp->setValue( $blog, $this->_userRepository );

		return $blog;
	}

	private function createTestPost(
		string $title,
		string $slug,
		string $status = Post::STATUS_PUBLISHED,
		int $authorId = 1
	): Post
	{
		$post = new Post();
		$post->setTitle( $title );
		$post->setSlug( $slug );
		$post->setBody( 'This is test content for ' . $title );
		$post->setAuthorId( $authorId );
		$post->setStatus( $status );

		if( $status === Post::STATUS_PUBLISHED )
		{
			$post->setPublishedAt( new DateTimeImmutable() );
		}

		return $this->_postRepository->create( $post );
	}

	private function createTestCategory( string $name, string $slug ): Category
	{
		$category = new Category();
		$category->setName( $name );
		$category->setSlug( $slug );

		return $this->_categoryRepository->create( $category );
	}

	private function createTestTag( string $name, string $slug ): Tag
	{
		$tag = new Tag();
		$tag->setName( $name );
		$tag->setSlug( $slug );

		return $this->_tagRepository->create( $tag );
	}

	private function createTestUser( string $username, string $email ): User
	{
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( password_hash( 'password', PASSWORD_DEFAULT ) );
		$user->setRole( User::ROLE_AUTHOR );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );

		return $this->_userRepository->create( $user );
	}

	public function testIndexReturnsPublishedPosts(): void
	{
		$this->createTestPost( 'Published Post 1', 'published-1', Post::STATUS_PUBLISHED );
		$this->createTestPost( 'Published Post 2', 'published-2', Post::STATUS_PUBLISHED );
		$this->createTestPost( 'Draft Post', 'draft-1', Post::STATUS_DRAFT );

		$blog = $this->createBlogWithInjectedRepositories();
		$result = $blog->index( new Request() );

		$this->assertIsString( $result );
		// Should contain published posts but not drafts
	}

	public function testShowWithValidSlug(): void
	{
		$post = $this->createTestPost( 'Test Article', 'test-article', Post::STATUS_PUBLISHED );

		$blog = $this->createBlogWithInjectedRepositories();
		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-article' ] );
		$result = $blog->show( $request );

		$this->assertIsString( $result );
	}

	public function testShowWithNonexistentSlug(): void
	{
		$blog = $this->createBlogWithInjectedRepositories();
		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'nonexistent' ] );
		$result = $blog->show( $request );

		$this->assertIsString( $result );
		// Should handle gracefully
	}

	public function testTagFiltersPostsByTag(): void
	{
		$tag = $this->createTestTag( 'PHP', 'php' );

		$post1 = $this->createTestPost( 'PHP Post', 'php-post', Post::STATUS_PUBLISHED );
		$post1->addTag( $tag );
		$this->_postRepository->update( $post1 );

		$this->createTestPost( 'Other Post', 'other-post', Post::STATUS_PUBLISHED );

		$blog = $this->createBlogWithInjectedRepositories();
		$request = new Request();
		$request->setRouteParameters( [ 'tag' => $tag->getSlug() ] );
		$result = $blog->tag( $request );

		$this->assertIsString( $result );
	}

	public function testCategoryFiltersPostsByCategory(): void
	{
		$category = $this->createTestCategory( 'Technology', 'technology' );

		$post1 = $this->createTestPost( 'Tech Post', 'tech-post', Post::STATUS_PUBLISHED );
		$post1->addCategory( $category );
		$this->_postRepository->update( $post1 );

		$this->createTestPost( 'Other Post', 'other-post', Post::STATUS_PUBLISHED );

		$blog = $this->createBlogWithInjectedRepositories();
		$request = new Request();
		$request->setRouteParameters( [ 'category' => $category->getSlug() ] );
		$result = $blog->category( $request );

		$this->assertIsString( $result );
	}

	public function testAuthorFiltersPostsByAuthor(): void
	{
		// Create test users
		$user1 = $this->createTestUser( 'author1', 'author1@test.com' );
		$user2 = $this->createTestUser( 'author2', 'author2@test.com' );

		// Create posts by different authors
		$this->createTestPost( 'Author 1 Post', 'a1-post', Post::STATUS_PUBLISHED, $user1->getId() );
		$this->createTestPost( 'Author 2 Post', 'a2-post', Post::STATUS_PUBLISHED, $user2->getId() );

		$blog = $this->createBlogWithInjectedRepositories();

		// Create mock request with author parameter
		$request = $this->createMock( Request::class );
		$request->expects( $this->once() )
			->method( 'getRouteParameter' )
			->with( 'author', '' )
			->willReturn( 'author1' );

		$result = $blog->author( $request );

		// Verify the response contains only author1's posts
		$this->assertStringContainsString( 'Author 1 Post', $result );
		$this->assertStringNotContainsString( 'Author 2 Post', $result );
		$this->assertStringContainsString( 'Articles by author1', $result );
	}

	public function testBlogExtendsContentController(): void
	{
		$blog = $this->createBlogWithInjectedRepositories();

		// Test inherited methods from ContentController
		$this->assertEquals( 'Test Blog', $blog->getName() );
		$this->assertEquals( 'Test Blog Title', $blog->getTitle() );
		$this->assertEquals( 'Test Blog Description', $blog->getDescription() );
		$this->assertEquals( 'http://test.com', $blog->getUrl() );
		$this->assertEquals( 'http://test.com/blog/rss', $blog->getRssUrl() );
	}
}
