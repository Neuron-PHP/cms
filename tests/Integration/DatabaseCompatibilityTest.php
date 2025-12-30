<?php

namespace Tests\Integration;

use Neuron\Cms\Models\User;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Exceptions\DuplicateEntityException;

/**
 * Database Compatibility Integration Tests
 *
 * Ensures consistent behavior across SQLite, MySQL, and PostgreSQL:
 * - Foreign key constraint enforcement
 * - Timestamp auto-update behavior
 * - Cascade delete operations
 * - Transaction support
 */
class DatabaseCompatibilityTest extends IntegrationTestCase
{
	private DatabaseUserRepository $_userRepo;
	private DatabasePostRepository $_postRepo;
	private DatabaseCategoryRepository $_categoryRepo;
	private DatabaseTagRepository $_tagRepo;
	private DatabasePageRepository $_pageRepo;

	protected function setUp(): void
	{
		parent::setUp();

		// Set up ORM with test PDO connection
		\Neuron\Orm\Model::setPdo( $this->pdo );

		// Create repositories using reflection to inject the shared PDO
		// This ensures repositories use the same connection where migrations ran
		$this->_userRepo = $this->createRepositoryWithPdo( DatabaseUserRepository::class );
		$this->_postRepo = $this->createRepositoryWithPdo( DatabasePostRepository::class );
		$this->_categoryRepo = $this->createRepositoryWithPdo( DatabaseCategoryRepository::class );
		$this->_tagRepo = $this->createRepositoryWithPdo( DatabaseTagRepository::class );
		$this->_pageRepo = $this->createRepositoryWithPdo( DatabasePageRepository::class );
	}

	/**
	 * Create a repository instance and inject the shared test PDO
	 *
	 * Uses reflection to set the private $_pdo property to ensure
	 * the repository uses the same connection where migrations ran.
	 * Some repositories (like DatabasePageRepository) only use ORM and don't have $_pdo.
	 *
	 * IMPORTANT: Repository constructors call Model::setPdo() with their own connection,
	 * so we must re-set the test PDO AFTER construction to override it.
	 *
	 * @param string $repositoryClass Fully qualified repository class name
	 * @return object Repository instance with shared PDO
	 */
	private function createRepositoryWithPdo( string $repositoryClass ): object
	{
		// Create minimal settings (adapter is enough for constructor)
		$source = new \Neuron\Data\Settings\Source\Memory();
		$source->set( 'database', 'adapter', 'sqlite' );
		$source->set( 'database', 'name', ':memory:' ); // Won't be used
		$settings = new \Neuron\Data\Settings\SettingManager( $source );

		// Create repository instance (constructor will call Model::setPdo with a new connection)
		$repository = new $repositoryClass( $settings );

		// Use reflection to replace the PDO connection with our shared test PDO (if it has one)
		$reflection = new \ReflectionClass( $repository );
		if( $reflection->hasProperty( '_pdo' ) )
		{
			$pdoProperty = $reflection->getProperty( '_pdo' );
			$pdoProperty->setAccessible( true );
			$pdoProperty->setValue( $repository, $this->pdo );
		}

		// CRITICAL: Re-set the ORM Model PDO to the test PDO
		// Repository constructor called Model::setPdo() with a different connection,
		// so we must override it to use the test database where migrations ran
		\Neuron\Orm\Model::setPdo( $this->pdo );

		return $repository;
	}

	/**
	 * Test: SQLite foreign keys are enforced with SET NULL
	 *
	 * Critical for data integrity - when users are deleted, posts should remain
	 * but have their author_id set to NULL (content preservation).
	 */
	public function testForeignKeyConstraintsAreEnforced(): void
	{
		// Create a user
		$user = new User();
		$user->setUsername( 'fk_test_user' );
		$user->setEmail( 'fk@test.com' );
		$user->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user->setRole( User::ROLE_AUTHOR );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user = $this->_userRepo->create( $user );

		// Create a post by this user
		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post-fk' );
		$post->setBody( 'Test content' );
		$post->setAuthorId( $user->getId() );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setPublishedAt( new \DateTimeImmutable() );
		$post = $this->_postRepo->create( $post );

		$postId = $post->getId();

		// Delete the user - post should have author_id set to NULL
		$this->_userRepo->delete( $user->getId() );

		// Verify post still exists but author_id is NULL
		$foundPost = $this->_postRepo->findById( $postId );
		$this->assertNotNull( $foundPost, 'Post should still exist after user deletion' );
		$this->assertEquals( 0, $foundPost->getAuthorId(), 'Author ID should be 0 (NULL) after user deletion' );
	}

	/**
	 * Test: updated_at timestamps are properly maintained
	 *
	 * Since we removed MySQL-specific ON UPDATE CURRENT_TIMESTAMP,
	 * timestamps must be handled at the application level
	 */
	public function testUpdatedAtTimestampsWork(): void
	{
		// Test User updated_at
		$user = new User();
		$user->setUsername( 'timestamp_test' );
		$user->setEmail( 'timestamp@test.com' );
		$user->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user->setRole( User::ROLE_AUTHOR );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user = $this->_userRepo->create( $user );

		$originalUpdatedAt = $user->getUpdatedAt();

		// Wait a moment and update
		sleep( 1 );
		$user->setEmail( 'newemail@test.com' );
		$this->_userRepo->update( $user );

		// Verify updated_at changed
		$updatedUser = $this->_userRepo->findById( $user->getId() );
		$this->assertNotNull( $updatedUser );
		$this->assertGreaterThan(
			$originalUpdatedAt->getTimestamp(),
			$updatedUser->getUpdatedAt()->getTimestamp(),
			'updated_at should be updated when user is modified'
		);
	}

	/**
	 * Test: Category updated_at handling
	 */
	public function testCategoryUpdatedAtWorks(): void
	{
		$category = new Category();
		$category->setName( 'Test Category' );
		$category->setSlug( 'test-category-timestamp' );
		$category->setDescription( 'Original description' );
		$category = $this->_categoryRepo->create( $category );

		$originalUpdatedAt = $category->getUpdatedAt();

		sleep( 1 );
		$category->setDescription( 'Updated description' );
		$this->_categoryRepo->update( $category );

		$updated = $this->_categoryRepo->findById( $category->getId() );
		$this->assertGreaterThan(
			$originalUpdatedAt->getTimestamp(),
			$updated->getUpdatedAt()->getTimestamp(),
			'Category updated_at should update'
		);
	}

	/**
	 * Test: Tag updated_at handling
	 */
	public function testTagUpdatedAtWorks(): void
	{
		$tag = new Tag();
		$tag->setName( 'Test Tag' );
		$tag->setSlug( 'test-tag-timestamp' );
		$tag = $this->_tagRepo->create( $tag );

		$originalUpdatedAt = $tag->getUpdatedAt();

		sleep( 1 );
		$tag->setName( 'Updated Tag' );
		$this->_tagRepo->update( $tag );

		$updated = $this->_tagRepo->findById( $tag->getId() );
		$this->assertGreaterThan(
			$originalUpdatedAt->getTimestamp(),
			$updated->getUpdatedAt()->getTimestamp(),
			'Tag updated_at should update'
		);
	}

	/**
	 * Test: Page updated_at handling
	 */
	public function testPageUpdatedAtWorks(): void
	{
		// Create author
		$user = new User();
		$user->setUsername( 'page_author' );
		$user->setEmail( 'pageauthor@test.com' );
		$user->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user->setRole( User::ROLE_AUTHOR );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user = $this->_userRepo->create( $user );

		$page = new Page();
		$page->setTitle( 'Test Page' );
		$page->setSlug( 'test-page-timestamp' );
		$page->setContent( '{"blocks":[]}' );
		$page->setAuthorId( $user->getId() );
		$page->setStatus( Page::STATUS_PUBLISHED );
		$page = $this->_pageRepo->create( $page );

		$originalUpdatedAt = $page->getUpdatedAt();

		sleep( 1 );
		$page->setTitle( 'Updated Page Title' );
		$this->_pageRepo->update( $page );

		$updated = $this->_pageRepo->findById( $page->getId() );
		$this->assertGreaterThan(
			$originalUpdatedAt->getTimestamp(),
			$updated->getUpdatedAt()->getTimestamp(),
			'Page updated_at should update'
		);
	}

	/**
	 * Test: Post updated_at handling (already implemented)
	 */
	public function testPostUpdatedAtWorks(): void
	{
		// Create author
		$user = new User();
		$user->setUsername( 'post_author' );
		$user->setEmail( 'postauthor@test.com' );
		$user->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user->setRole( User::ROLE_AUTHOR );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user = $this->_userRepo->create( $user );

		$post = new Post();
		$post->setTitle( 'Timestamp Test Post' );
		$post->setSlug( 'timestamp-test-post' );
		$post->setBody( 'Original content' );
		$post->setAuthorId( $user->getId() );
		$post->setStatus( Post::STATUS_DRAFT );
		$post = $this->_postRepo->create( $post );

		$originalUpdatedAt = $post->getUpdatedAt();

		sleep( 1 );
		$post->setBody( 'Updated content' );
		$this->_postRepo->update( $post );

		$updated = $this->_postRepo->findById( $post->getId() );
		$this->assertGreaterThan(
			$originalUpdatedAt->getTimestamp(),
			$updated->getUpdatedAt()->getTimestamp(),
			'Post updated_at should update'
		);
	}

	/**
	 * Test: Transaction rollback works correctly
	 */
	public function testTransactionRollbackWorks(): void
	{
		try
		{
			User::transaction( function() {
				$user = new User();
				$user->setUsername( 'rollback_test' );
				$user->setEmail( 'rollback@test.com' );
				$user->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
				$user->setRole( User::ROLE_AUTHOR );
				$user->setStatus( User::STATUS_ACTIVE );
				$user->setEmailVerified( true );
				User::create( $user->toArray() );

				// Force an exception
				throw new \Exception( 'Test rollback' );
			} );
		}
		catch( \Exception $e )
		{
			// Expected
		}

		// Verify user was not created
		$user = $this->_userRepo->findByUsername( 'rollback_test' );
		$this->assertNull( $user, 'User should not exist after transaction rollback' );
	}

	/**
	 * Test: Unique constraints are enforced
	 */
	public function testUniqueConstraintsEnforced(): void
	{
		$user1 = new User();
		$user1->setUsername( 'unique_test' );
		$user1->setEmail( 'unique@test.com' );
		$user1->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user1->setRole( User::ROLE_AUTHOR );
		$user1->setStatus( User::STATUS_ACTIVE );
		$user1->setEmailVerified( true );
		$this->_userRepo->create( $user1 );

		// Try to create duplicate username
		$this->expectException( DuplicateEntityException::class );
		$this->expectExceptionMessage( "Duplicate User: username 'unique_test' already exists" );

		$user2 = new User();
		$user2->setUsername( 'unique_test' ); // Duplicate!
		$user2->setEmail( 'unique2@test.com' );
		$user2->setPasswordHash( password_hash( 'test123', PASSWORD_DEFAULT ) );
		$user2->setRole( User::ROLE_AUTHOR );
		$user2->setStatus( User::STATUS_ACTIVE );
		$user2->setEmailVerified( true );
		$this->_userRepo->create( $user2 );
	}

	/**
	 * Test: Database-specific optimizations are applied
	 *
	 * This verifies ConnectionFactory initialization worked
	 */
	public function testDatabaseOptimizationsApplied(): void
	{
		$pdo = $this->pdo;
		$driver = $pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );

		if( $driver === 'sqlite' )
		{
			// Check foreign keys are enabled
			$stmt = $pdo->query( 'PRAGMA foreign_keys' );
			$result = $stmt->fetch( \PDO::FETCH_ASSOC );
			$this->assertEquals( 1, $result['foreign_keys'], 'SQLite foreign keys should be enabled' );

			// Check WAL mode
			$stmt = $pdo->query( 'PRAGMA journal_mode' );
			$result = $stmt->fetch( \PDO::FETCH_ASSOC );
			$this->assertEquals( 'wal', strtolower( $result['journal_mode'] ), 'SQLite should use WAL mode' );
		}

		// All databases should have exception mode
		$this->assertEquals(
			\PDO::ERRMODE_EXCEPTION,
			$pdo->getAttribute( \PDO::ATTR_ERRMODE ),
			'PDO should use exception error mode'
		);
	}
}
