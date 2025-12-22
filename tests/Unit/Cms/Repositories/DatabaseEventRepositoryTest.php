<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Models\User;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;
use DateTimeImmutable;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseEventRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseEventRepository $repository;

	protected function setUp(): void
	{
		// Create in-memory SQLite database
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// Create event_categories table
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

		// Create users table
		$this->pdo->exec( "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				role VARCHAR(50) NOT NULL,
				status VARCHAR(50) NOT NULL,
				email_verified BOOLEAN DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		// Create events table
		$this->pdo->exec( "
			CREATE TABLE events (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				description TEXT,
				content_raw TEXT NOT NULL DEFAULT '{\"blocks\":[]}',
				location VARCHAR(500),
				start_date TIMESTAMP NOT NULL,
				end_date TIMESTAMP,
				all_day BOOLEAN DEFAULT 0,
				category_id INTEGER,
				status VARCHAR(20) DEFAULT 'draft',
				featured_image VARCHAR(255),
				organizer VARCHAR(255),
				contact_email VARCHAR(255),
				contact_phone VARCHAR(50),
				created_by INTEGER,
				view_count INTEGER DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL,
				FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
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
		$this->repository = new DatabaseEventRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setAccessible( true );
		$property->setValue( $this->repository, $this->pdo );
	}

	/**
	 * Helper to create a test category
	 */
	private function createTestCategory( string $name = 'Test Category' ): int
	{
		$this->pdo->exec( "INSERT INTO event_categories (name, slug) VALUES ('{$name}', '" . strtolower( str_replace( ' ', '-', $name ) ) . "')" );
		return (int)$this->pdo->lastInsertId();
	}

	/**
	 * Helper to create a test user
	 */
	private function createTestUser( string $username = 'testuser' ): int
	{
		$email = $username . '@test.com';
		$this->pdo->exec( "INSERT INTO users (username, email, password_hash, role, status, email_verified)
			VALUES ('{$username}', '{$email}', 'hash', 'author', 'active', 1)" );
		return (int)$this->pdo->lastInsertId();
	}

	public function test_all_returns_empty_array_when_no_events(): void
	{
		$events = $this->repository->all();

		$this->assertIsArray( $events );
		$this->assertEmpty( $events );
	}

	public function test_all_returns_events_sorted_by_start_date_desc(): void
	{
		$userId = $this->createTestUser();

		// Insert events with different start dates
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Event 1', 'event-1', '2025-01-01 10:00:00', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Event 2', 'event-2', '2025-06-15 14:00:00', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Event 3', 'event-3', '2025-03-20 09:00:00', 'published', {$userId})" );

		$events = $this->repository->all();

		$this->assertCount( 3, $events );
		$this->assertEquals( 'Event 2', $events[0]->getTitle() ); // Most recent first
		$this->assertEquals( 'Event 3', $events[1]->getTitle() );
		$this->assertEquals( 'Event 1', $events[2]->getTitle() );
	}

	public function test_find_by_id_returns_event_when_found(): void
	{
		$userId = $this->createTestUser();
		$categoryId = $this->createTestCategory();

		$this->pdo->exec( "INSERT INTO events (title, slug, description, content_raw, location, start_date, end_date,
			all_day, category_id, status, featured_image, organizer, contact_email, contact_phone, created_by, view_count)
			VALUES ('Tech Conference', 'tech-conference', 'A great tech event', '{\"blocks\":[]}', 'Convention Center',
			'2025-06-15 09:00:00', '2025-06-15 17:00:00', 0, {$categoryId}, 'published', '/images/tech.jpg',
			'Tech Org', 'info@tech.com', '555-1234', {$userId}, 42)" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );

		$this->assertInstanceOf( Event::class, $event );
		$this->assertEquals( $id, $event->getId() );
		$this->assertEquals( 'Tech Conference', $event->getTitle() );
		$this->assertEquals( 'tech-conference', $event->getSlug() );
		$this->assertEquals( 'A great tech event', $event->getDescription() );
		$this->assertEquals( 'Convention Center', $event->getLocation() );
		$this->assertFalse( $event->isAllDay() );
		$this->assertEquals( 'published', $event->getStatus() );
		$this->assertEquals( 42, $event->getViewCount() );

		// Check relations are loaded
		$this->assertInstanceOf( EventCategory::class, $event->getCategory() );
		$this->assertInstanceOf( User::class, $event->getCreator() );
	}

	public function test_find_by_id_returns_null_when_not_found(): void
	{
		$event = $this->repository->findById( 999 );

		$this->assertNull( $event );
	}

	public function test_find_by_slug_returns_event_when_found(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Workshop', 'workshop-event', '2025-07-01 10:00:00', 'published', {$userId})" );

		$event = $this->repository->findBySlug( 'workshop-event' );

		$this->assertInstanceOf( Event::class, $event );
		$this->assertEquals( 'Workshop', $event->getTitle() );
		$this->assertEquals( 'workshop-event', $event->getSlug() );
	}

	public function test_find_by_slug_returns_null_when_not_found(): void
	{
		$event = $this->repository->findBySlug( 'nonexistent-slug' );

		$this->assertNull( $event );
	}

	public function test_get_by_category_returns_events_in_category(): void
	{
		$userId = $this->createTestUser();
		$categoryId1 = $this->createTestCategory( 'Tech' );
		$categoryId2 = $this->createTestCategory( 'Business' );

		// Create events in different categories
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Tech Event 1', 'tech-1', '2025-06-15 10:00:00', {$categoryId1}, 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Business Event', 'biz-1', '2025-07-01 10:00:00', {$categoryId2}, 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Tech Event 2', 'tech-2', '2025-08-01 10:00:00', {$categoryId1}, 'published', {$userId})" );

		$events = $this->repository->getByCategory( $categoryId1 );

		$this->assertCount( 2, $events );
		$this->assertEquals( 'Tech Event 1', $events[0]->getTitle() );
		$this->assertEquals( 'Tech Event 2', $events[1]->getTitle() );
	}

	public function test_get_by_category_filters_by_status(): void
	{
		$userId = $this->createTestUser();
		$categoryId = $this->createTestCategory();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Published', 'pub-1', '2025-06-15 10:00:00', {$categoryId}, 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Draft', 'draft-1', '2025-07-01 10:00:00', {$categoryId}, 'draft', {$userId})" );

		$events = $this->repository->getByCategory( $categoryId, 'published' );

		$this->assertCount( 1, $events );
		$this->assertEquals( 'Published', $events[0]->getTitle() );
	}

	public function test_get_upcoming_returns_future_events(): void
	{
		$userId = $this->createTestUser();

		// Past event
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Past Event', 'past-1', '2020-01-01 10:00:00', 'published', {$userId})" );

		// Future events
		$futureDate1 = ( new DateTimeImmutable( '+1 week' ) )->format( 'Y-m-d H:i:s' );
		$futureDate2 = ( new DateTimeImmutable( '+2 weeks' ) )->format( 'Y-m-d H:i:s' );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Future Event 1', 'future-1', '{$futureDate1}', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Future Event 2', 'future-2', '{$futureDate2}', 'published', {$userId})" );

		$events = $this->repository->getUpcoming();

		$this->assertCount( 2, $events );
		$this->assertEquals( 'Future Event 1', $events[0]->getTitle() ); // Soonest first
		$this->assertEquals( 'Future Event 2', $events[1]->getTitle() );
	}

	public function test_get_upcoming_respects_limit(): void
	{
		$userId = $this->createTestUser();

		for( $i = 1; $i <= 5; $i++ )
		{
			$futureDate = ( new DateTimeImmutable( "+{$i} week" ) )->format( 'Y-m-d H:i:s' );
			$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
				VALUES ('Event {$i}', 'event-{$i}', '{$futureDate}', 'published', {$userId})" );
		}

		$events = $this->repository->getUpcoming( 3 );

		$this->assertCount( 3, $events );
	}

	public function test_get_past_returns_past_events(): void
	{
		$userId = $this->createTestUser();

		// Future event
		$futureDate = ( new DateTimeImmutable( '+1 week' ) )->format( 'Y-m-d H:i:s' );
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Future Event', 'future', '{$futureDate}', 'published', {$userId})" );

		// Past events
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, end_date, status, created_by)
			VALUES ('Past Event 1', 'past-1', '2024-01-01 10:00:00', '2024-01-01 17:00:00', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Past Event 2', 'past-2', '2024-06-15 10:00:00', 'published', {$userId})" );

		$events = $this->repository->getPast();

		$this->assertCount( 2, $events );
		// Should be sorted by start_date DESC (most recent first)
		$this->assertEquals( 'Past Event 2', $events[0]->getTitle() );
		$this->assertEquals( 'Past Event 1', $events[1]->getTitle() );
	}

	public function test_get_past_respects_limit(): void
	{
		$userId = $this->createTestUser();

		for( $i = 1; $i <= 5; $i++ )
		{
			$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
				VALUES ('Past Event {$i}', 'past-{$i}', '2024-0{$i}-01 10:00:00', 'published', {$userId})" );
		}

		$events = $this->repository->getPast( 2 );

		$this->assertCount( 2, $events );
	}

	public function test_get_by_date_range_returns_events_in_range(): void
	{
		$userId = $this->createTestUser();

		// Events in different date ranges
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, end_date, status, created_by)
			VALUES ('January Event', 'jan', '2025-01-15 10:00:00', '2025-01-15 17:00:00', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, end_date, status, created_by)
			VALUES ('June Event', 'june', '2025-06-15 10:00:00', '2025-06-15 17:00:00', 'published', {$userId})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, end_date, status, created_by)
			VALUES ('December Event', 'dec', '2025-12-15 10:00:00', '2025-12-15 17:00:00', 'published', {$userId})" );

		$startDate = new DateTimeImmutable( '2025-06-01' );
		$endDate = new DateTimeImmutable( '2025-06-30' );

		$events = $this->repository->getByDateRange( $startDate, $endDate );

		$this->assertCount( 1, $events );
		$this->assertEquals( 'June Event', $events[0]->getTitle() );
	}

	public function test_get_by_date_range_includes_spanning_events(): void
	{
		$userId = $this->createTestUser();

		// Event that spans the entire range
		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, end_date, status, created_by)
			VALUES ('Long Event', 'long', '2025-05-01 10:00:00', '2025-07-31 17:00:00', 'published', {$userId})" );

		$startDate = new DateTimeImmutable( '2025-06-01' );
		$endDate = new DateTimeImmutable( '2025-06-30' );

		$events = $this->repository->getByDateRange( $startDate, $endDate );

		$this->assertCount( 1, $events );
		$this->assertEquals( 'Long Event', $events[0]->getTitle() );
	}

	public function test_get_by_creator_returns_user_events(): void
	{
		$userId1 = $this->createTestUser( 'user1' );
		$userId2 = $this->createTestUser( 'user2' );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('User 1 Event A', 'u1-a', '2025-06-15 10:00:00', 'published', {$userId1})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('User 2 Event', 'u2-a', '2025-07-01 10:00:00', 'published', {$userId2})" );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('User 1 Event B', 'u1-b', '2025-08-01 10:00:00', 'published', {$userId1})" );

		$events = $this->repository->getByCreator( $userId1 );

		$this->assertCount( 2, $events );
		// Should be sorted by start_date DESC
		$this->assertEquals( 'User 1 Event B', $events[0]->getTitle() );
		$this->assertEquals( 'User 1 Event A', $events[1]->getTitle() );
	}

	public function test_create_inserts_event_and_sets_id(): void
	{
		$userId = $this->createTestUser();

		$event = new Event();
		$event->setTitle( 'New Event' );
		$event->setSlug( 'new-event' );
		$event->setDescription( 'A test event' );
		$event->setContent( '{"blocks":[]}' );
		$event->setLocation( 'Test Location' );
		$event->setStartDate( new DateTimeImmutable( '2025-06-15 10:00:00' ) );
		$event->setEndDate( new DateTimeImmutable( '2025-06-15 17:00:00' ) );
		$event->setAllDay( false );
		$event->setStatus( Event::STATUS_PUBLISHED );
		$event->setCreatedBy( $userId );

		$result = $this->repository->create( $event );

		$this->assertInstanceOf( Event::class, $result );
		$this->assertNotNull( $result->getId() );
		$this->assertGreaterThan( 0, $result->getId() );
		$this->assertInstanceOf( DateTimeImmutable::class, $result->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $result->getUpdatedAt() );

		// Verify in database
		$stmt = $this->pdo->prepare( "SELECT * FROM events WHERE id = ?" );
		$stmt->execute( [ $result->getId() ] );
		$row = $stmt->fetch();

		$this->assertEquals( 'New Event', $row['title'] );
		$this->assertEquals( 'new-event', $row['slug'] );
		$this->assertEquals( 'Test Location', $row['location'] );
	}

	public function test_create_sets_timestamps(): void
	{
		$userId = $this->createTestUser();

		$event = new Event();
		$event->setTitle( 'Test' );
		$event->setSlug( 'test' );
		$event->setStartDate( new DateTimeImmutable( '2025-06-15 10:00:00' ) );
		$event->setCreatedBy( $userId );

		$beforeCreate = new DateTimeImmutable();
		$result = $this->repository->create( $event );
		$afterCreate = new DateTimeImmutable();

		$this->assertGreaterThanOrEqual( $beforeCreate, $result->getCreatedAt() );
		$this->assertLessThanOrEqual( $afterCreate, $result->getCreatedAt() );
		$this->assertEquals( $result->getCreatedAt(), $result->getUpdatedAt() );
	}

	public function test_update_modifies_event(): void
	{
		$userId = $this->createTestUser();

		// Create initial event
		$this->pdo->exec( "INSERT INTO events (title, slug, description, start_date, status, created_by)
			VALUES ('Original Title', 'original', 'Original description', '2025-06-15 10:00:00', 'draft', {$userId})" );
		$id = (int)$this->pdo->lastInsertId();

		// Fetch and modify
		$event = $this->repository->findById( $id );
		$event->setTitle( 'Updated Title' );
		$event->setDescription( 'Updated description' );
		$event->setStatus( Event::STATUS_PUBLISHED );

		$result = $this->repository->update( $event );

		$this->assertInstanceOf( Event::class, $result );
		$this->assertInstanceOf( DateTimeImmutable::class, $result->getUpdatedAt() );

		// Verify in database
		$stmt = $this->pdo->prepare( "SELECT * FROM events WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		$this->assertEquals( 'Updated Title', $row['title'] );
		$this->assertEquals( 'Updated description', $row['description'] );
		$this->assertEquals( Event::STATUS_PUBLISHED, $row['status'] );
	}

	public function test_update_sets_updated_at_timestamp(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, created_by, created_at, updated_at)
			VALUES ('Test', 'test', '2025-06-15 10:00:00', {$userId}, '2024-01-01 10:00:00', '2024-01-01 10:00:00')" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );
		$originalUpdatedAt = $event->getUpdatedAt();

		sleep( 1 ); // Ensure timestamp changes

		$event->setTitle( 'Modified' );
		$result = $this->repository->update( $event );

		$this->assertGreaterThan( $originalUpdatedAt, $result->getUpdatedAt() );
	}

	public function test_delete_removes_event(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('To Delete', 'to-delete', '2025-06-15 10:00:00', 'draft', {$userId})" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );
		$result = $this->repository->delete( $event );

		$this->assertTrue( $result );

		// Verify not in database
		$found = $this->repository->findById( $id );
		$this->assertNull( $found );
	}

	public function test_slug_exists_returns_true_when_slug_exists(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Test', 'existing-slug', '2025-06-15 10:00:00', 'published', {$userId})" );

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
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Test1', 'test-slug', '2025-06-15 10:00:00', 'published', {$userId})" );
		$id1 = (int)$this->pdo->lastInsertId();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('Test2', 'different-slug', '2025-06-15 10:00:00', 'published', {$userId})" );
		$id2 = (int)$this->pdo->lastInsertId();

		// Should return false when excluding the ID that has the slug
		$exists = $this->repository->slugExists( 'test-slug', $id1 );
		$this->assertFalse( $exists );

		// Should return true when excluding a different ID
		$exists = $this->repository->slugExists( 'test-slug', $id2 );
		$this->assertTrue( $exists );
	}

	public function test_increment_view_count_updates_database_and_model(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by, view_count)
			VALUES ('Test Event', 'test', '2025-06-15 10:00:00', 'published', {$userId}, 10)" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );
		$this->assertEquals( 10, $event->getViewCount() );

		$this->repository->incrementViewCount( $event );

		$this->assertEquals( 11, $event->getViewCount() );

		// Verify in database
		$stmt = $this->pdo->prepare( "SELECT view_count FROM events WHERE id = ?" );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch();

		$this->assertEquals( 11, $row['view_count'] );
	}

	public function test_load_relations_includes_category_and_creator(): void
	{
		$userId = $this->createTestUser( 'eventcreator' );
		$categoryId = $this->createTestCategory( 'Conferences' );

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, category_id, status, created_by)
			VALUES ('Conference Event', 'conf', '2025-06-15 10:00:00', {$categoryId}, 'published', {$userId})" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );

		// Verify category relation
		$this->assertInstanceOf( EventCategory::class, $event->getCategory() );
		$this->assertEquals( 'Conferences', $event->getCategory()->getName() );

		// Verify creator relation
		$this->assertInstanceOf( User::class, $event->getCreator() );
		$this->assertEquals( 'eventcreator', $event->getCreator()->getUsername() );
	}

	public function test_load_relations_handles_null_category(): void
	{
		$userId = $this->createTestUser();

		$this->pdo->exec( "INSERT INTO events (title, slug, start_date, status, created_by)
			VALUES ('No Category Event', 'no-cat', '2025-06-15 10:00:00', 'published', {$userId})" );
		$id = (int)$this->pdo->lastInsertId();

		$event = $this->repository->findById( $id );

		$this->assertNull( $event->getCategory() );
		$this->assertInstanceOf( User::class, $event->getCreator() );
	}
}
