<?php

namespace Tests\Cms\Repositories;

use DateTimeImmutable;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\DatabasePostRepository;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabasePostRepositoryTest extends TestCase
{
	private PDO $_PDO;
	private DatabasePostRepository $_Repository;

	protected function setUp(): void
	{
		// Create in-memory SQLite database for testing
		$this->_PDO = new PDO(
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

		// Initialize repository with in-memory database
		// Create a test subclass that allows PDO injection
		$pdo = $this->_PDO;
		$this->_Repository = new class( $pdo ) extends DatabasePostRepository
		{
			public function __construct( PDO $PDO )
			{
				// Skip parent constructor and directly assign PDO
				$reflection = new \ReflectionClass( DatabasePostRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};
	}

	private function createTables(): void
	{
		// Create posts table
		$this->_PDO->exec( "
			CREATE TABLE posts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL UNIQUE,
				body TEXT NOT NULL,
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
		$this->_PDO->exec( "
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
		$this->_PDO->exec( "
			CREATE TABLE tags (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(100) NOT NULL,
				slug VARCHAR(100) NOT NULL UNIQUE,
				created_at TIMESTAMP NOT NULL,
				updated_at TIMESTAMP
			)
		" );

		// Create junction tables
		$this->_PDO->exec( "
			CREATE TABLE post_categories (
				post_id INTEGER NOT NULL,
				category_id INTEGER NOT NULL,
				created_at TIMESTAMP NOT NULL,
				PRIMARY KEY (post_id, category_id),
				FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
				FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
			)
		" );

		$this->_PDO->exec( "
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

	private function createCategory( string $name, string $slug ): Category
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO categories (name, slug, created_at) VALUES (?, ?, ?)"
		);
		$stmt->execute( [ $name, $slug, ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ) ] );

		$category = new Category();
		$category->setId( (int)$this->_PDO->lastInsertId() );
		$category->setName( $name );
		$category->setSlug( $slug );

		return $category;
	}

	private function createTag( string $name, string $slug ): Tag
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO tags (name, slug, created_at) VALUES (?, ?, ?)"
		);
		$stmt->execute( [ $name, $slug, ( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' ) ] );

		$tag = new Tag();
		$tag->setId( (int)$this->_PDO->lastInsertId() );
		$tag->setName( $name );
		$tag->setSlug( $slug );

		return $tag;
	}

	public function testCanCreatePost(): void
	{
		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setBody( 'This is a test post body.' );
		$post->setAuthorId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$createdPost = $this->_Repository->create( $post );

		$this->assertNotNull( $createdPost->getId() );
		$this->assertEquals( 'Test Post', $createdPost->getTitle() );
		$this->assertEquals( 'test-post', $createdPost->getSlug() );
	}

	public function testCanCreatePostWithCategories(): void
	{
		$category1 = $this->createCategory( 'Tech', 'tech' );
		$category2 = $this->createCategory( 'News', 'news' );

		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setBody( 'Body content' );
		$post->setAuthorId( 1 );
		$post->addCategory( $category1 );
		$post->addCategory( $category2 );

		$createdPost = $this->_Repository->create( $post );

		$this->assertNotNull( $createdPost->getId() );
		$this->assertCount( 2, $createdPost->getCategories() );
	}

	public function testCanCreatePostWithTags(): void
	{
		$tag1 = $this->createTag( 'PHP', 'php' );
		$tag2 = $this->createTag( 'Testing', 'testing' );

		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setBody( 'Body content' );
		$post->setAuthorId( 1 );
		$post->addTag( $tag1 );
		$post->addTag( $tag2 );

		$createdPost = $this->_Repository->create( $post );

		$this->assertNotNull( $createdPost->getId() );
		$this->assertCount( 2, $createdPost->getTags() );
	}

	public function testCreateThrowsExceptionForDuplicateSlug(): void
	{
		$post1 = new Post();
		$post1->setTitle( 'First Post' );
		$post1->setSlug( 'duplicate-slug' );
		$post1->setBody( 'Body' );
		$post1->setAuthorId( 1 );
		$this->_Repository->create( $post1 );

		$post2 = new Post();
		$post2->setTitle( 'Second Post' );
		$post2->setSlug( 'duplicate-slug' );
		$post2->setBody( 'Body' );
		$post2->setAuthorId( 1 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->create( $post2 );
	}

	public function testCanFindPostById(): void
	{
		$post = new Post();
		$post->setTitle( 'Find Me' );
		$post->setSlug( 'find-me' );
		$post->setBody( 'Body content' );
		$post->setAuthorId( 1 );

		$created = $this->_Repository->create( $post );
		$found = $this->_Repository->findById( $created->getId() );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Find Me', $found->getTitle() );
		$this->assertEquals( 'find-me', $found->getSlug() );
	}

	public function testFindByIdReturnsNullForNonexistentPost(): void
	{
		$found = $this->_Repository->findById( 9999 );
		$this->assertNull( $found );
	}

	public function testCanFindPostBySlug(): void
	{
		$post = new Post();
		$post->setTitle( 'Slugged Post' );
		$post->setSlug( 'slugged-post' );
		$post->setBody( 'Body content' );
		$post->setAuthorId( 1 );

		$this->_Repository->create( $post );
		$found = $this->_Repository->findBySlug( 'slugged-post' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Slugged Post', $found->getTitle() );
	}

	public function testFindBySlugReturnsNullForNonexistentSlug(): void
	{
		$found = $this->_Repository->findBySlug( 'nonexistent-slug' );
		$this->assertNull( $found );
	}

	public function testCanUpdatePost(): void
	{
		$post = new Post();
		$post->setTitle( 'Original Title' );
		$post->setSlug( 'original-title' );
		$post->setBody( 'Original body' );
		$post->setAuthorId( 1 );

		$created = $this->_Repository->create( $post );
		$created->setTitle( 'Updated Title' );
		$created->setBody( 'Updated body' );

		$result = $this->_Repository->update( $created );

		$this->assertTrue( $result );

		$updated = $this->_Repository->findById( $created->getId() );
		$this->assertEquals( 'Updated Title', $updated->getTitle() );
		$this->assertEquals( 'Updated body', $updated->getBody() );
	}

	public function testCanUpdatePostCategories(): void
	{
		$category1 = $this->createCategory( 'Cat1', 'cat1' );
		$category2 = $this->createCategory( 'Cat2', 'cat2' );
		$category3 = $this->createCategory( 'Cat3', 'cat3' );

		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setBody( 'Body' );
		$post->setAuthorId( 1 );
		$post->addCategory( $category1 );

		$created = $this->_Repository->create( $post );
		$this->assertCount( 1, $created->getCategories() );

		// Update categories
		$created->removeCategory( $category1 );
		$created->addCategory( $category2 );
		$created->addCategory( $category3 );

		$this->_Repository->update( $created );

		$updated = $this->_Repository->findById( $created->getId() );
		$this->assertCount( 2, $updated->getCategories() );
	}

	public function testUpdateThrowsExceptionForDuplicateSlug(): void
	{
		$post1 = new Post();
		$post1->setTitle( 'First Post' );
		$post1->setSlug( 'first-post' );
		$post1->setBody( 'Body' );
		$post1->setAuthorId( 1 );
		$created1 = $this->_Repository->create( $post1 );

		$post2 = new Post();
		$post2->setTitle( 'Second Post' );
		$post2->setSlug( 'second-post' );
		$post2->setBody( 'Body' );
		$post2->setAuthorId( 1 );
		$created2 = $this->_Repository->create( $post2 );

		// Try to update second post with first post's slug
		$created2->setSlug( 'first-post' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->update( $created2 );
	}

	public function testUpdateReturnsFalseForPostWithoutId(): void
	{
		$post = new Post();
		$post->setTitle( 'Test' );
		$post->setSlug( 'test' );
		$post->setBody( 'Body' );

		$result = $this->_Repository->update( $post );
		$this->assertFalse( $result );
	}

	public function testCanDeletePost(): void
	{
		$post = new Post();
		$post->setTitle( 'Delete Me' );
		$post->setSlug( 'delete-me' );
		$post->setBody( 'Body' );
		$post->setAuthorId( 1 );

		$created = $this->_Repository->create( $post );
		$result = $this->_Repository->delete( $created->getId() );

		$this->assertTrue( $result );
		$this->assertNull( $this->_Repository->findById( $created->getId() ) );
	}

	public function testDeleteReturnsFalseForNonexistentPost(): void
	{
		$result = $this->_Repository->delete( 9999 );
		$this->assertFalse( $result );
	}

	public function testCanGetAllPosts(): void
	{
		$this->createTestPost( 'Post 1', 'post-1' );
		$this->createTestPost( 'Post 2', 'post-2' );
		$this->createTestPost( 'Post 3', 'post-3' );

		$posts = $this->_Repository->all();

		$this->assertCount( 3, $posts );
	}

	public function testCanGetAllPostsWithStatus(): void
	{
		$this->createTestPost( 'Draft 1', 'draft-1', Post::STATUS_DRAFT );
		$this->createTestPost( 'Published 1', 'published-1', Post::STATUS_PUBLISHED );
		$this->createTestPost( 'Published 2', 'published-2', Post::STATUS_PUBLISHED );

		$published = $this->_Repository->all( Post::STATUS_PUBLISHED );
		$drafts = $this->_Repository->all( Post::STATUS_DRAFT );

		$this->assertCount( 2, $published );
		$this->assertCount( 1, $drafts );
	}

	public function testCanGetAllPostsWithLimitAndOffset(): void
	{
		$this->createTestPost( 'Post 1', 'post-1' );
		$this->createTestPost( 'Post 2', 'post-2' );
		$this->createTestPost( 'Post 3', 'post-3' );
		$this->createTestPost( 'Post 4', 'post-4' );

		$posts = $this->_Repository->all( null, 2, 1 );

		$this->assertCount( 2, $posts );
	}

	public function testCanGetPostsByAuthor(): void
	{
		$this->createTestPost( 'Author 1 Post 1', 'a1-p1', Post::STATUS_PUBLISHED, 1 );
		$this->createTestPost( 'Author 1 Post 2', 'a1-p2', Post::STATUS_PUBLISHED, 1 );
		$this->createTestPost( 'Author 2 Post 1', 'a2-p1', Post::STATUS_PUBLISHED, 2 );

		$author1Posts = $this->_Repository->getByAuthor( 1 );
		$author2Posts = $this->_Repository->getByAuthor( 2 );

		$this->assertCount( 2, $author1Posts );
		$this->assertCount( 1, $author2Posts );
	}

	public function testCanGetPostsByAuthorWithStatus(): void
	{
		$this->createTestPost( 'Draft', 'draft', Post::STATUS_DRAFT, 1 );
		$this->createTestPost( 'Published', 'published', Post::STATUS_PUBLISHED, 1 );

		$drafts = $this->_Repository->getByAuthor( 1, Post::STATUS_DRAFT );
		$published = $this->_Repository->getByAuthor( 1, Post::STATUS_PUBLISHED );

		$this->assertCount( 1, $drafts );
		$this->assertCount( 1, $published );
	}

	public function testCanGetPostsByCategory(): void
	{
		$category = $this->createCategory( 'Tech', 'tech' );

		$post1 = $this->createTestPost( 'Post 1', 'post-1' );
		$post1->addCategory( $category );
		$this->_Repository->update( $post1 );

		$post2 = $this->createTestPost( 'Post 2', 'post-2' );

		$categoryPosts = $this->_Repository->getByCategory( $category->getId() );

		$this->assertCount( 1, $categoryPosts );
		$this->assertEquals( 'Post 1', $categoryPosts[0]->getTitle() );
	}

	public function testCanGetPostsByTag(): void
	{
		$tag = $this->createTag( 'PHP', 'php' );

		$post1 = $this->createTestPost( 'Post 1', 'post-1' );
		$post1->addTag( $tag );
		$this->_Repository->update( $post1 );

		$post2 = $this->createTestPost( 'Post 2', 'post-2' );

		$taggedPosts = $this->_Repository->getByTag( $tag->getId() );

		$this->assertCount( 1, $taggedPosts );
		$this->assertEquals( 'Post 1', $taggedPosts[0]->getTitle() );
	}

	public function testCanGetPublishedPosts(): void
	{
		$this->createTestPost( 'Draft', 'draft', Post::STATUS_DRAFT );
		$this->createTestPost( 'Published 1', 'pub-1', Post::STATUS_PUBLISHED );
		$this->createTestPost( 'Published 2', 'pub-2', Post::STATUS_PUBLISHED );

		$published = $this->_Repository->getPublished();

		$this->assertCount( 2, $published );
	}

	public function testCanGetDraftPosts(): void
	{
		$this->createTestPost( 'Draft 1', 'draft-1', Post::STATUS_DRAFT );
		$this->createTestPost( 'Draft 2', 'draft-2', Post::STATUS_DRAFT );
		$this->createTestPost( 'Published', 'published', Post::STATUS_PUBLISHED );

		$drafts = $this->_Repository->getDrafts();

		$this->assertCount( 2, $drafts );
	}

	public function testCanGetScheduledPosts(): void
	{
		$this->createTestPost( 'Scheduled 1', 'sched-1', Post::STATUS_SCHEDULED );
		$this->createTestPost( 'Published', 'published', Post::STATUS_PUBLISHED );

		$scheduled = $this->_Repository->getScheduled();

		$this->assertCount( 1, $scheduled );
	}

	public function testCanCountPosts(): void
	{
		$this->createTestPost( 'Post 1', 'post-1' );
		$this->createTestPost( 'Post 2', 'post-2' );

		$count = $this->_Repository->count();

		$this->assertEquals( 2, $count );
	}

	public function testCanCountPostsByStatus(): void
	{
		$this->createTestPost( 'Draft', 'draft', Post::STATUS_DRAFT );
		$this->createTestPost( 'Published 1', 'pub-1', Post::STATUS_PUBLISHED );
		$this->createTestPost( 'Published 2', 'pub-2', Post::STATUS_PUBLISHED );

		$draftCount = $this->_Repository->count( Post::STATUS_DRAFT );
		$publishedCount = $this->_Repository->count( Post::STATUS_PUBLISHED );

		$this->assertEquals( 1, $draftCount );
		$this->assertEquals( 2, $publishedCount );
	}

	public function testCanIncrementViewCount(): void
	{
		$post = $this->createTestPost( 'Test Post', 'test-post' );
		$post->setViewCount( 5 );
		$this->_Repository->update( $post );

		$result = $this->_Repository->incrementViewCount( $post->getId() );

		$this->assertTrue( $result );

		$updated = $this->_Repository->findById( $post->getId() );
		$this->assertEquals( 6, $updated->getViewCount() );
	}

	public function testIncrementViewCountReturnsFalseForNonexistentPost(): void
	{
		$result = $this->_Repository->incrementViewCount( 9999 );
		$this->assertFalse( $result );
	}

	public function testCanAttachCategories(): void
	{
		$category1 = $this->createCategory( 'Cat1', 'cat1' );
		$category2 = $this->createCategory( 'Cat2', 'cat2' );
		$post = $this->createTestPost( 'Test Post', 'test-post' );

		$result = $this->_Repository->attachCategories( $post->getId(), [
			$category1->getId(),
			$category2->getId()
		] );

		$this->assertTrue( $result );

		$reloaded = $this->_Repository->findById( $post->getId() );
		$this->assertCount( 2, $reloaded->getCategories() );
	}

	public function testCanDetachCategories(): void
	{
		$category = $this->createCategory( 'Cat1', 'cat1' );
		$post = $this->createTestPost( 'Test Post', 'test-post' );
		$this->_Repository->attachCategories( $post->getId(), [ $category->getId() ] );

		$result = $this->_Repository->detachCategories( $post->getId() );

		$this->assertTrue( $result );

		$reloaded = $this->_Repository->findById( $post->getId() );
		$this->assertCount( 0, $reloaded->getCategories() );
	}

	public function testCanAttachTags(): void
	{
		$tag1 = $this->createTag( 'Tag1', 'tag1' );
		$tag2 = $this->createTag( 'Tag2', 'tag2' );
		$post = $this->createTestPost( 'Test Post', 'test-post' );

		$result = $this->_Repository->attachTags( $post->getId(), [
			$tag1->getId(),
			$tag2->getId()
		] );

		$this->assertTrue( $result );

		$reloaded = $this->_Repository->findById( $post->getId() );
		$this->assertCount( 2, $reloaded->getTags() );
	}

	public function testCanDetachTags(): void
	{
		$tag = $this->createTag( 'Tag1', 'tag1' );
		$post = $this->createTestPost( 'Test Post', 'test-post' );
		$this->_Repository->attachTags( $post->getId(), [ $tag->getId() ] );

		$result = $this->_Repository->detachTags( $post->getId() );

		$this->assertTrue( $result );

		$reloaded = $this->_Repository->findById( $post->getId() );
		$this->assertCount( 0, $reloaded->getTags() );
	}

	private function createTestPost(
		string $title,
		string $slug,
		string $status = Post::STATUS_DRAFT,
		int $authorId = 1
	): Post
	{
		$post = new Post();
		$post->setTitle( $title );
		$post->setSlug( $slug );
		$post->setBody( 'Test body content' );
		$post->setAuthorId( $authorId );
		$post->setStatus( $status );

		return $this->_Repository->create( $post );
	}
}
