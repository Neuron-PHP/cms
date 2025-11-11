<?php

namespace Tests\Cms\Repositories;

use DateTimeImmutable;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseCategoryRepositoryTest extends TestCase
{
	private PDO $_PDO;
	private DatabaseCategoryRepository $_Repository;

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
		$this->_Repository = new class( $pdo ) extends DatabaseCategoryRepository
		{
			public function __construct( PDO $PDO )
			{
				// Skip parent constructor and directly assign PDO
				$reflection = new \ReflectionClass( DatabaseCategoryRepository::class );
				$property = $reflection->getProperty( '_pdo' );
				$property->setAccessible( true );
				$property->setValue( $this, $PDO );
			}
		};
	}

	private function createTables(): void
	{
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
			CREATE TABLE post_categories (
				post_id INTEGER NOT NULL,
				category_id INTEGER NOT NULL,
				created_at TIMESTAMP NOT NULL,
				PRIMARY KEY (post_id, category_id),
				FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
				FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
			)
		" );
	}

	public function testCanCreateCategory(): void
	{
		$category = new Category();
		$category->setName( 'Technology' );
		$category->setSlug( 'technology' );
		$category->setDescription( 'Tech articles' );

		$created = $this->_Repository->create( $category );

		$this->assertNotNull( $created->getId() );
		$this->assertEquals( 'Technology', $created->getName() );
		$this->assertEquals( 'technology', $created->getSlug() );
		$this->assertEquals( 'Tech articles', $created->getDescription() );
	}

	public function testCreateThrowsExceptionForDuplicateSlug(): void
	{
		$category1 = new Category();
		$category1->setName( 'First' );
		$category1->setSlug( 'duplicate-slug' );
		$this->_Repository->create( $category1 );

		$category2 = new Category();
		$category2->setName( 'Second' );
		$category2->setSlug( 'duplicate-slug' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->create( $category2 );
	}

	public function testCreateThrowsExceptionForDuplicateName(): void
	{
		$category1 = new Category();
		$category1->setName( 'Duplicate Name' );
		$category1->setSlug( 'slug-one' );
		$this->_Repository->create( $category1 );

		$category2 = new Category();
		$category2->setName( 'Duplicate Name' );
		$category2->setSlug( 'slug-two' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Category name already exists' );
		$this->_Repository->create( $category2 );
	}

	public function testCanFindCategoryById(): void
	{
		$category = new Category();
		$category->setName( 'Find Me' );
		$category->setSlug( 'find-me' );

		$created = $this->_Repository->create( $category );
		$found = $this->_Repository->findById( $created->getId() );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Find Me', $found->getName() );
		$this->assertEquals( 'find-me', $found->getSlug() );
	}

	public function testFindByIdReturnsNullForNonexistentCategory(): void
	{
		$found = $this->_Repository->findById( 9999 );
		$this->assertNull( $found );
	}

	public function testCanFindCategoryBySlug(): void
	{
		$category = new Category();
		$category->setName( 'Slugged Category' );
		$category->setSlug( 'slugged-category' );

		$this->_Repository->create( $category );
		$found = $this->_Repository->findBySlug( 'slugged-category' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'Slugged Category', $found->getName() );
	}

	public function testFindBySlugReturnsNullForNonexistentSlug(): void
	{
		$found = $this->_Repository->findBySlug( 'nonexistent-slug' );
		$this->assertNull( $found );
	}

	public function testCanFindCategoryByName(): void
	{
		$category = new Category();
		$category->setName( 'Unique Name' );
		$category->setSlug( 'unique-name' );

		$this->_Repository->create( $category );
		$found = $this->_Repository->findByName( 'Unique Name' );

		$this->assertNotNull( $found );
		$this->assertEquals( 'unique-name', $found->getSlug() );
	}

	public function testFindByNameReturnsNullForNonexistentName(): void
	{
		$found = $this->_Repository->findByName( 'Nonexistent Name' );
		$this->assertNull( $found );
	}

	public function testCanUpdateCategory(): void
	{
		$category = new Category();
		$category->setName( 'Original Name' );
		$category->setSlug( 'original-slug' );
		$category->setDescription( 'Original description' );

		$created = $this->_Repository->create( $category );
		$created->setName( 'Updated Name' );
		$created->setDescription( 'Updated description' );

		$result = $this->_Repository->update( $created );

		$this->assertTrue( $result );

		$updated = $this->_Repository->findById( $created->getId() );
		$this->assertEquals( 'Updated Name', $updated->getName() );
		$this->assertEquals( 'Updated description', $updated->getDescription() );
	}

	public function testUpdateThrowsExceptionForDuplicateSlug(): void
	{
		$category1 = new Category();
		$category1->setName( 'First' );
		$category1->setSlug( 'first-slug' );
		$created1 = $this->_Repository->create( $category1 );

		$category2 = new Category();
		$category2->setName( 'Second' );
		$category2->setSlug( 'second-slug' );
		$created2 = $this->_Repository->create( $category2 );

		// Try to update second category with first category's slug
		$created2->setSlug( 'first-slug' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );
		$this->_Repository->update( $created2 );
	}

	public function testUpdateThrowsExceptionForDuplicateName(): void
	{
		$category1 = new Category();
		$category1->setName( 'First Name' );
		$category1->setSlug( 'first' );
		$created1 = $this->_Repository->create( $category1 );

		$category2 = new Category();
		$category2->setName( 'Second Name' );
		$category2->setSlug( 'second' );
		$created2 = $this->_Repository->create( $category2 );

		// Try to update second category with first category's name
		$created2->setName( 'First Name' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Category name already exists' );
		$this->_Repository->update( $created2 );
	}

	public function testUpdateReturnsFalseForCategoryWithoutId(): void
	{
		$category = new Category();
		$category->setName( 'Test' );
		$category->setSlug( 'test' );

		$result = $this->_Repository->update( $category );
		$this->assertFalse( $result );
	}

	public function testCanDeleteCategory(): void
	{
		$category = new Category();
		$category->setName( 'Delete Me' );
		$category->setSlug( 'delete-me' );

		$created = $this->_Repository->create( $category );
		$result = $this->_Repository->delete( $created->getId() );

		$this->assertTrue( $result );
		$this->assertNull( $this->_Repository->findById( $created->getId() ) );
	}

	public function testDeleteReturnsFalseForNonexistentCategory(): void
	{
		$result = $this->_Repository->delete( 9999 );
		$this->assertFalse( $result );
	}

	public function testCanGetAllCategories(): void
	{
		$this->createTestCategory( 'Cat 1', 'cat-1' );
		$this->createTestCategory( 'Cat 2', 'cat-2' );
		$this->createTestCategory( 'Cat 3', 'cat-3' );

		$categories = $this->_Repository->all();

		$this->assertCount( 3, $categories );
	}

	public function testAllCategoriesAreSortedByName(): void
	{
		$this->createTestCategory( 'Zebra', 'zebra' );
		$this->createTestCategory( 'Alpha', 'alpha' );
		$this->createTestCategory( 'Beta', 'beta' );

		$categories = $this->_Repository->all();

		$this->assertEquals( 'Alpha', $categories[0]->getName() );
		$this->assertEquals( 'Beta', $categories[1]->getName() );
		$this->assertEquals( 'Zebra', $categories[2]->getName() );
	}

	public function testCanCountCategories(): void
	{
		$this->createTestCategory( 'Cat 1', 'cat-1' );
		$this->createTestCategory( 'Cat 2', 'cat-2' );

		$count = $this->_Repository->count();

		$this->assertEquals( 2, $count );
	}

	public function testCanGetCategoriesWithPostCount(): void
	{
		// Create categories
		$cat1 = $this->createTestCategory( 'Cat 1', 'cat-1' );
		$cat2 = $this->createTestCategory( 'Cat 2', 'cat-2' );
		$cat3 = $this->createTestCategory( 'Cat 3', 'cat-3' );

		// Create posts
		$post1Id = $this->createTestPost( 'Post 1', 'post-1' );
		$post2Id = $this->createTestPost( 'Post 2', 'post-2' );
		$post3Id = $this->createTestPost( 'Post 3', 'post-3' );

		// Attach posts to categories
		$this->attachPostToCategory( $post1Id, $cat1->getId() );
		$this->attachPostToCategory( $post2Id, $cat1->getId() );
		$this->attachPostToCategory( $post3Id, $cat2->getId() );
		// cat3 has no posts

		$categoriesWithCount = $this->_Repository->allWithPostCount();

		$this->assertCount( 3, $categoriesWithCount );

		// Find Cat 1 (should have 2 posts)
		$cat1Data = array_values( array_filter( $categoriesWithCount, fn( $c ) => $c['category']->getId() === $cat1->getId() ) )[0];
		$this->assertEquals( 2, $cat1Data['post_count'] );

		// Find Cat 2 (should have 1 post)
		$cat2Data = array_values( array_filter( $categoriesWithCount, fn( $c ) => $c['category']->getId() === $cat2->getId() ) )[0];
		$this->assertEquals( 1, $cat2Data['post_count'] );

		// Find Cat 3 (should have 0 posts)
		$cat3Data = array_values( array_filter( $categoriesWithCount, fn( $c ) => $c['category']->getId() === $cat3->getId() ) )[0];
		$this->assertEquals( 0, $cat3Data['post_count'] );
	}

	public function testCanCreateCategoryWithNullDescription(): void
	{
		$category = new Category();
		$category->setName( 'No Description' );
		$category->setSlug( 'no-description' );
		$category->setDescription( null );

		$created = $this->_Repository->create( $category );

		$this->assertNotNull( $created->getId() );
		$this->assertNull( $created->getDescription() );
	}

	private function createTestCategory( string $name, string $slug ): Category
	{
		$category = new Category();
		$category->setName( $name );
		$category->setSlug( $slug );

		return $this->_Repository->create( $category );
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

	private function attachPostToCategory( int $postId, int $categoryId ): void
	{
		$stmt = $this->_PDO->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)"
		);
		$stmt->execute( [
			$postId,
			$categoryId,
			( new DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
		] );
	}
}
