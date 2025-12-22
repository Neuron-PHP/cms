<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Models\EventCategory;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseEventCategoryRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseEventCategoryRepository $repository;

	protected function setUp(): void
	{
		// Create in-memory SQLite database
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// Create table
		$this->pdo->exec( "
			CREATE TABLE event_categories (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				color VARCHAR(7) DEFAULT '#3b82f6',
				description TEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		// Mock settings
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name' => ':memory:'
			] );

		// Create repository and inject PDO via reflection
		$this->repository = new DatabaseEventCategoryRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setAccessible( true );
		$property->setValue( $this->repository, $this->pdo );
	}

	public function test_all_returns_empty_array_when_no_categories(): void
	{
		$categories = $this->repository->all();

		$this->assertIsArray( $categories );
		$this->assertEmpty( $categories );
	}

	public function test_all_returns_categories_sorted_by_name(): void
	{
		// Insert test data
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Workshops', 'workshops')" );
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Conferences', 'conferences')" );
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Meetups', 'meetups')" );

		$categories = $this->repository->all();

		$this->assertCount( 3, $categories );
		$this->assertEquals( 'Conferences', $categories[0]->getName() );
		$this->assertEquals( 'Meetups', $categories[1]->getName() );
		$this->assertEquals( 'Workshops', $categories[2]->getName() );
	}

	public function test_find_by_id_returns_category_when_found(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug, color, description)
			VALUES ('Tech Events', 'tech-events', '#ff0000', 'Technology related events')" );
		$id = (int)$this->pdo->lastInsertId();

		$category = $this->repository->findById( $id );

		$this->assertInstanceOf( EventCategory::class, $category );
		$this->assertEquals( $id, $category->getId() );
		$this->assertEquals( 'Tech Events', $category->getName() );
		$this->assertEquals( 'tech-events', $category->getSlug() );
		$this->assertEquals( '#ff0000', $category->getColor() );
		$this->assertEquals( 'Technology related events', $category->getDescription() );
	}

	public function test_find_by_id_returns_null_when_not_found(): void
	{
		$category = $this->repository->findById( 999 );

		$this->assertNull( $category );
	}

	public function test_find_by_slug_returns_category_when_found(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Webinars', 'webinars')" );

		$category = $this->repository->findBySlug( 'webinars' );

		$this->assertInstanceOf( EventCategory::class, $category );
		$this->assertEquals( 'Webinars', $category->getName() );
		$this->assertEquals( 'webinars', $category->getSlug() );
	}

	public function test_find_by_slug_returns_null_when_not_found(): void
	{
		$category = $this->repository->findBySlug( 'nonexistent' );

		$this->assertNull( $category );
	}

	public function test_find_by_ids_returns_empty_array_for_empty_input(): void
	{
		$categories = $this->repository->findByIds( [] );

		$this->assertIsArray( $categories );
		$this->assertEmpty( $categories );
	}

	public function test_find_by_ids_returns_matching_categories(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Cat1', 'cat1')" );
		$id1 = (int)$this->pdo->lastInsertId();

		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Cat2', 'cat2')" );
		$id2 = (int)$this->pdo->lastInsertId();

		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Cat3', 'cat3')" );
		$id3 = (int)$this->pdo->lastInsertId();

		$categories = $this->repository->findByIds( [ $id1, $id3 ] );

		$this->assertCount( 2, $categories );
		$this->assertEquals( $id1, $categories[0]->getId() );
		$this->assertEquals( $id3, $categories[1]->getId() );
	}

	public function test_find_by_ids_returns_empty_array_when_none_found(): void
	{
		$categories = $this->repository->findByIds( [ 999, 888 ] );

		$this->assertIsArray( $categories );
		$this->assertEmpty( $categories );
	}

	public function test_create_inserts_category_and_sets_id(): void
	{
		$category = new EventCategory();
		$category->setName( 'New Category' );
		$category->setSlug( 'new-category' );
		$category->setColor( '#00ff00' );
		$category->setDescription( 'A test category' );

		$result = $this->repository->create( $category );

		$this->assertInstanceOf( EventCategory::class, $result );
		$this->assertNotNull( $result->getId() );
		$this->assertGreaterThan( 0, $result->getId() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $result->getCreatedAt() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $result->getUpdatedAt() );

		// Verify in database
		$stmt = $this->pdo->prepare( "SELECT * FROM event_categories WHERE id = ?" );
		$stmt->execute( [ $result->getId() ] );
		$row = $stmt->fetch();

		$this->assertEquals( 'New Category', $row['name'] );
		$this->assertEquals( 'new-category', $row['slug'] );
		$this->assertEquals( '#00ff00', $row['color'] );
		$this->assertEquals( 'A test category', $row['description'] );
	}

	public function test_create_sets_timestamps(): void
	{
		$category = new EventCategory();
		$category->setName( 'Test' );
		$category->setSlug( 'test' );

		$beforeCreate = new \DateTimeImmutable();
		$result = $this->repository->create( $category );
		$afterCreate = new \DateTimeImmutable();

		$this->assertGreaterThanOrEqual( $beforeCreate, $result->getCreatedAt() );
		$this->assertLessThanOrEqual( $afterCreate, $result->getCreatedAt() );
		$this->assertEquals( $result->getCreatedAt(), $result->getUpdatedAt() );
	}

	public function test_update_modifies_category(): void
	{
		// Create initial category
		$this->pdo->exec( "INSERT INTO event_categories (name, slug, color, description)
			VALUES ('Original', 'original', '#000000', 'Original description')" );
		$id = (int)$this->pdo->lastInsertId();

		// Fetch and modify
		$category = $this->repository->findById( $id );
		$category->setName( 'Updated Name' );
		$category->setSlug( 'updated-slug' );
		$category->setColor( '#ffffff' );
		$category->setDescription( 'Updated description' );

		$result = $this->repository->update( $category );

		$this->assertInstanceOf( EventCategory::class, $result );
		$this->assertInstanceOf( \DateTimeImmutable::class, $result->getUpdatedAt() );

		// Verify in database
		$stmt = $this->pdo->prepare( "SELECT * FROM event_categories WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		$this->assertEquals( 'Updated Name', $row['name'] );
		$this->assertEquals( 'updated-slug', $row['slug'] );
		$this->assertEquals( '#ffffff', $row['color'] );
		$this->assertEquals( 'Updated description', $row['description'] );
	}

	public function test_update_sets_updated_at_timestamp(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug, created_at, updated_at)
			VALUES ('Test', 'test', '2024-01-01 10:00:00', '2024-01-01 10:00:00')" );
		$id = (int)$this->pdo->lastInsertId();

		$category = $this->repository->findById( $id );
		$originalUpdatedAt = $category->getUpdatedAt();

		sleep( 1 ); // Ensure timestamp changes

		$category->setName( 'Modified' );
		$result = $this->repository->update( $category );

		$this->assertGreaterThan( $originalUpdatedAt, $result->getUpdatedAt() );
	}

	public function test_delete_removes_category(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('To Delete', 'to-delete')" );
		$id = (int)$this->pdo->lastInsertId();

		$category = $this->repository->findById( $id );
		$result = $this->repository->delete( $category );

		$this->assertTrue( $result );

		// Verify not in database
		$found = $this->repository->findById( $id );
		$this->assertNull( $found );
	}

	public function test_slug_exists_returns_true_when_slug_exists(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Test', 'existing-slug')" );

		$exists = $this->repository->slugExists( 'existing-slug' );

		$this->assertTrue( $exists );
	}

	public function test_slug_exists_returns_false_when_slug_does_not_exist(): void
	{
		$exists = $this->repository->slugExists( 'nonexistent-slug' );

		$this->assertFalse( $exists );
	}

	public function test_slug_exists_excludes_specified_id(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Test1', 'test-slug')" );
		$id1 = (int)$this->pdo->lastInsertId();

		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Test2', 'different-slug')" );
		$id2 = (int)$this->pdo->lastInsertId();

		// Should return false when excluding the ID that has the slug
		$exists = $this->repository->slugExists( 'test-slug', $id1 );
		$this->assertFalse( $exists );

		// Should return true when excluding a different ID
		$exists = $this->repository->slugExists( 'test-slug', $id2 );
		$this->assertTrue( $exists );
	}

	public function test_slug_exists_without_exclude_id(): void
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('Test', 'test-slug')" );

		$exists = $this->repository->slugExists( 'test-slug', null );

		$this->assertTrue( $exists );
	}
}
