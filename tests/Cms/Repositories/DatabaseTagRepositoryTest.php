<?php

namespace Tests\Cms\Repositories;

use DateTimeImmutable;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Orm\Model;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseTagRepositoryTest extends TestCase
{
	private PDO $_PDO;
	private DatabaseTagRepository $_Repository;

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

		// Initialize ORM with the PDO connection
		Model::setPdo( $this->_PDO );

		// Initialize repository with in-memory database
		// Create a test subclass that allows PDO injection
		$pdo = $this->_PDO;
		$this->_Repository = new class( $pdo ) extends DatabaseTagRepository
		{
			public function __construct( PDO $PDO )
			{
				// Skip parent constructor and directly assign PDO
				$reflection = new \ReflectionClass( DatabaseTagRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};
	}

	private function createTables(): void
	{
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

		// Create posts and junction table for post count testing
		$this->_PDO->exec( "
			CREATE TABLE posts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title VARCHAR(255) NOT NULL,
				slug VARCHAR(255) NOT NULL UNIQUE,
				body TEXT NOT NULL,
				author_id INTEGER NOT NULL,
				created_at TIMESTAMP NOT NULL
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

	public function testCanCreateTag(): void
	{
		$tag = new Tag();
		$tag->setName( 'PHP' );
		$tag->setSlug( 'php' );

		$created = $this->_Repository->create( $tag );

		$this->assertNotNull( $created->getId() );
		$this->assertEquals( 'PHP', $created->getName() );
		$this->assertEquals( 'php', $created->getSlug() );
	}

	public function testCreateThrowsExceptionForDuplicateSlug(): void
	{
		$tag1 = new Tag();
		$tag1->setName( 'First' );
		$tag1->setSlug( 'duplicate-slug' );
		$this->_Repository->create( $tag1 );

		$tag2 = new Tag();
		$tag2->setName( 'Second' );
		$tag2->setSlug( 'duplicate-slug' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->create( $tag2 );
	}

	public function testCreateThrowsExceptionForDuplicateName(): void
	{
		$tag1 = new Tag();
		$tag1->setName( 'Duplicate Name' );
		$tag1->setSlug( 'slug-one' );
		$this->_Repository->create( $tag1 );

		$tag2 = new Tag();
		$tag2->setName( 'Duplicate Name' );
		$tag2->setSlug( 'slug-two' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Tag name already exists' );
		$this->_Repository->create( $tag2 );
	}

	public function testCanFindTagById(): void
	{
		$tag = new Tag();
		$tag->setName( 'Find Me' );
		$tag->setSlug( 'find-me' );

		$created = $this->_Repository->create( $tag );
		$found = $this->_Repository->findById( $created->getId() );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Find Me', $found->getName() );
		$this->assertEquals( 'find-me', $found->getSlug() );
	}

	public function testFindByIdReturnsNullForNonexistentTag(): void
	{
		$found = $this->_Repository->findById( 9999 );
		$this->assertNull( $found );
	}

	public function testCanFindTagBySlug(): void
	{
		$tag = new Tag();
		$tag->setName( 'Slugged Tag' );
		$tag->setSlug( 'slugged-tag' );

		$this->_Repository->create( $tag );
		$found = $this->_Repository->findBySlug( 'slugged-tag' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Slugged Tag', $found->getName() );
	}

	public function testFindBySlugReturnsNullForNonexistentSlug(): void
	{
		$found = $this->_Repository->findBySlug( 'nonexistent-slug' );
		$this->assertNull( $found );
	}

	public function testCanFindTagByName(): void
	{
		$tag = new Tag();
		$tag->setName( 'Unique Name' );
		$tag->setSlug( 'unique-name' );

		$this->_Repository->create( $tag );
		$found = $this->_Repository->findByName( 'Unique Name' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'unique-name', $found->getSlug() );
	}

	public function testFindByNameReturnsNullForNonexistentName(): void
	{
		$found = $this->_Repository->findByName( 'Nonexistent Name' );
		$this->assertNull( $found );
	}

	public function testCanUpdateTag(): void
	{
		$tag = new Tag();
		$tag->setName( 'Original Name' );
		$tag->setSlug( 'original-slug' );

		$created = $this->_Repository->create( $tag );
		$created->setName( 'Updated Name' );

		$result = $this->_Repository->update( $created );

		$this->assertTrue( $result );

		$updated = $this->_Repository->findById( $created->getId() );
		$this->assertEquals( 'Updated Name', $updated->getName() );
	}

	public function testUpdateThrowsExceptionForDuplicateSlug(): void
	{
		$tag1 = new Tag();
		$tag1->setName( 'First' );
		$tag1->setSlug( 'first-slug' );
		$created1 = $this->_Repository->create( $tag1 );

		$tag2 = new Tag();
		$tag2->setName( 'Second' );
		$tag2->setSlug( 'second-slug' );
		$created2 = $this->_Repository->create( $tag2 );

		// Try to update second tag with first tag's slug
		$created2->setSlug( 'first-slug' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->update( $created2 );
	}

	public function testUpdateThrowsExceptionForDuplicateName(): void
	{
		$tag1 = new Tag();
		$tag1->setName( 'First Name' );
		$tag1->setSlug( 'first' );
		$created1 = $this->_Repository->create( $tag1 );

		$tag2 = new Tag();
		$tag2->setName( 'Second Name' );
		$tag2->setSlug( 'second' );
		$created2 = $this->_Repository->create( $tag2 );

		// Try to update second tag with first tag's name
		$created2->setName( 'First Name' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Tag name already exists' );
		$this->_Repository->update( $created2 );
	}

	public function testUpdateReturnsFalseForTagWithoutId(): void
	{
		$tag = new Tag();
		$tag->setName( 'Test' );
		$tag->setSlug( 'test' );

		$result = $this->_Repository->update( $tag );
		$this->assertFalse( $result );
	}

	public function testCanDeleteTag(): void
	{
		$tag = new Tag();
		$tag->setName( 'Delete Me' );
		$tag->setSlug( 'delete-me' );

		$created = $this->_Repository->create( $tag );
		$result = $this->_Repository->delete( $created->getId() );

		$this->assertTrue( $result );
		$this->assertNull( $this->_Repository->findById( $created->getId() ) );
	}

	public function testDeleteReturnsFalseForNonexistentTag(): void
	{
		$result = $this->_Repository->delete( 9999 );
		$this->assertFalse( $result );
	}

	public function testCanGetAllTags(): void
	{
		$this->createTestTag( 'Tag 1', 'tag-1' );
		$this->createTestTag( 'Tag 2', 'tag-2' );
		$this->createTestTag( 'Tag 3', 'tag-3' );

		$tags = $this->_Repository->all();

		$this->assertCount( 3, $tags );
	}

	public function testAllTagsAreSortedByName(): void
	{
		$this->createTestTag( 'Zebra', 'zebra' );
		$this->createTestTag( 'Alpha', 'alpha' );
		$this->createTestTag( 'Beta', 'beta' );

		$tags = $this->_Repository->all();

		$this->assertEquals( 'Alpha', $tags[0]->getName() );
		$this->assertEquals( 'Beta', $tags[1]->getName() );
		$this->assertEquals( 'Zebra', $tags[2]->getName() );
	}

	public function testCanCountTags(): void
	{
		$this->createTestTag( 'Tag 1', 'tag-1' );
		$this->createTestTag( 'Tag 2', 'tag-2' );

		$count = $this->_Repository->count();

		$this->assertEquals( 2, $count );
	}

	public function testCanGetTagsWithPostCount(): void
	{
		// Create tags
		$tag1 = $this->createTestTag( 'Tag 1', 'tag-1' );
		$tag2 = $this->createTestTag( 'Tag 2', 'tag-2' );
		$tag3 = $this->createTestTag( 'Tag 3', 'tag-3' );

		// Create posts
		$post1Id = $this->createTestPost( 'Post 1', 'post-1' );
		$post2Id = $this->createTestPost( 'Post 2', 'post-2' );
		$post3Id = $this->createTestPost( 'Post 3', 'post-3' );

		// Attach posts to tags
		$this->attachPostToTag( $post1Id, $tag1->getId() );
		$this->attachPostToTag( $post2Id, $tag1->getId() );
		$this->attachPostToTag( $post3Id, $tag2->getId() );
		// tag3 has no posts

		$tagsWithCount = $this->_Repository->allWithPostCount();

		$this->assertCount( 3, $tagsWithCount );

		// Find Tag 1 (should have 2 posts)
		$tag1Data = array_values( array_filter( $tagsWithCount, fn( $t ) => $t['tag']->getId() === $tag1->getId() ) )[0];
		$this->assertEquals( 2, $tag1Data['post_count'] );

		// Find Tag 2 (should have 1 post)
		$tag2Data = array_values( array_filter( $tagsWithCount, fn( $t ) => $t['tag']->getId() === $tag2->getId() ) )[0];
		$this->assertEquals( 1, $tag2Data['post_count'] );

		// Find Tag 3 (should have 0 posts)
		$tag3Data = array_values( array_filter( $tagsWithCount, fn( $t ) => $t['tag']->getId() === $tag3->getId() ) )[0];
		$this->assertEquals( 0, $tag3Data['post_count'] );
	}

	private function createTestTag( string $name, string $slug ): Tag
	{
		$tag = new Tag();
		$tag->setName( $name );
		$tag->setSlug( $slug );

		return $this->_Repository->create( $tag );
	}

	private function createTestPost( string $title, string $slug ): int
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO posts (title, slug, body, author_id, created_at) VALUES (?, ?, ?, ?, ?)"
		);
		$stmt->execute( [
			$title,
			$slug,
			'Test body',
			1,
			( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
		] );

		return (int)$this->_PDO->lastInsertId();
	}

	private function attachPostToTag( int $postId, int $tagId ): void
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)"
		);
		$stmt->execute( [
			$postId,
			$tagId,
			( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
		] );
	}
}
