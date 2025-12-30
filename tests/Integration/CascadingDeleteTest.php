<?php

namespace Tests\Integration;

/**
 * Integration test for cascading delete strategies.
 *
 * Tests ORM dependent strategies to ensure proper data integrity:
 * - User deletion nullifies posts/pages/events (DependentStrategy::Nullify)
 * - Category/Tag deletion removes pivot entries (DependentStrategy::DeleteAll)
 * - EventCategory deletion nullifies events (DependentStrategy::Nullify)
 *
 * Uses real database with actual migrations to test cascading behavior.
 *
 * @package Tests\Integration
 */
class CascadingDeleteTest extends IntegrationTestCase
{
	/**
	 * Test user deletion sets author_id to NULL on posts
	 */
	public function testUserDeletionNullifiesTostsAuthorId(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'postauthor',
			'email' => 'author@example.com'
		]);

		// Create post with this author
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Post', 'test-post', 'Content', '{"blocks":[]}', $userId, 'published', $now, $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Verify post has author
		$stmt = $this->pdo->prepare( "SELECT author_id FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();
		$this->assertEquals( $userId, $post['author_id'] );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify post still exists but author_id is NULL
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();
		$this->assertNotFalse( $post, 'Post should still exist' );
		$this->assertNull( $post['author_id'], 'Author ID should be NULL after user deletion' );
		$this->assertEquals( 'Test Post', $post['title'] );
	}

	/**
	 * Test user deletion sets author_id to NULL on pages
	 */
	public function testUserDeletionNullifiesPagesAuthorId(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'pageauthor',
			'email' => 'pageauthor@example.com'
		]);

		// Create page with this author
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, template, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Page', 'test-page', '{"blocks":[]}', $userId, 'published', 'default', $now, $now] );
		$pageId = (int)$this->pdo->lastInsertId();

		// Verify page has author
		$stmt = $this->pdo->prepare( "SELECT author_id FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();
		$this->assertEquals( $userId, $page['author_id'] );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify page still exists but author_id is NULL
		$stmt = $this->pdo->prepare( "SELECT * FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();
		$this->assertNotFalse( $page, 'Page should still exist' );
		$this->assertNull( $page['author_id'], 'Author ID should be NULL after user deletion' );
		$this->assertEquals( 'Test Page', $page['title'] );
	}

	/**
	 * Test user deletion sets created_by to NULL on events
	 */
	public function testUserDeletionNullifiesEventsCreatedBy(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'eventcreator',
			'email' => 'eventcreator@example.com'
		]);

		// Create event with this creator
		$now = date( 'Y-m-d H:i:s' );
		$startDate = date( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
		$stmt = $this->pdo->prepare(
			"INSERT INTO events (title, slug, description, content_raw, start_date, created_by, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Event', 'test-event', 'Event description', '{"blocks":[]}', $startDate, $userId, 'published', $now, $now] );
		$eventId = (int)$this->pdo->lastInsertId();

		// Verify event has creator
		$stmt = $this->pdo->prepare( "SELECT created_by FROM events WHERE id = ?" );
		$stmt->execute( [$eventId] );
		$event = $stmt->fetch();
		$this->assertEquals( $userId, $event['created_by'] );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify event still exists but created_by is NULL
		$stmt = $this->pdo->prepare( "SELECT * FROM events WHERE id = ?" );
		$stmt->execute( [$eventId] );
		$event = $stmt->fetch();
		$this->assertNotFalse( $event, 'Event should still exist' );
		$this->assertNull( $event['created_by'], 'Created by should be NULL after user deletion' );
		$this->assertEquals( 'Test Event', $event['title'] );
	}

	/**
	 * Test category deletion removes pivot entries but keeps posts
	 */
	public function testCategoryDeletionRemovesPivotEntries(): void
	{
		// Create user for post
		$userId = $this->createTestUser([
			'username' => 'catpostuser',
			'email' => 'catpost@example.com'
		]);

		// Create category
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Category', 'test-category', $now, $now] );
		$categoryId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Post', 'test-post-cat', 'Content', '{"blocks":[]}', $userId, 'published', $now, $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach category to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)"
		);
		$stmt->execute( [$postId, $categoryId] );

		// Verify pivot entry exists
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_categories WHERE post_id = ? AND category_id = ?" );
		$stmt->execute( [$postId, $categoryId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'] );

		// Delete category
		$stmt = $this->pdo->prepare( "DELETE FROM categories WHERE id = ?" );
		$stmt->execute( [$categoryId] );

		// Verify pivot entry is removed
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_categories WHERE category_id = ?" );
		$stmt->execute( [$categoryId] );
		$count = $stmt->fetch();
		$this->assertEquals( 0, $count['count'], 'Pivot entry should be deleted' );

		// Verify post still exists
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();
		$this->assertNotFalse( $post, 'Post should still exist after category deletion' );
		$this->assertEquals( 'Test Post', $post['title'] );
	}

	/**
	 * Test tag deletion removes pivot entries but keeps posts
	 */
	public function testTagDeletionRemovesPivotEntries(): void
	{
		// Create user for post
		$userId = $this->createTestUser([
			'username' => 'tagpostuser',
			'email' => 'tagpost@example.com'
		]);

		// Create tag
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Tag', 'test-tag', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Post', 'test-post-tag', 'Content', '{"blocks":[]}', $userId, 'published', $now, $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach tag to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)"
		);
		$stmt->execute( [$postId, $tagId] );

		// Verify pivot entry exists
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_tags WHERE post_id = ? AND tag_id = ?" );
		$stmt->execute( [$postId, $tagId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'] );

		// Delete tag
		$stmt = $this->pdo->prepare( "DELETE FROM tags WHERE id = ?" );
		$stmt->execute( [$tagId] );

		// Verify pivot entry is removed
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_tags WHERE tag_id = ?" );
		$stmt->execute( [$tagId] );
		$count = $stmt->fetch();
		$this->assertEquals( 0, $count['count'], 'Pivot entry should be deleted' );

		// Verify post still exists
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();
		$this->assertNotFalse( $post, 'Post should still exist after tag deletion' );
		$this->assertEquals( 'Test Post', $post['title'] );
	}

	/**
	 * Test post deletion removes both category and tag pivot entries
	 */
	public function testPostDeletionRemovesCategoryAndTagPivotEntries(): void
	{
		// Create user for post
		$userId = $this->createTestUser([
			'username' => 'pivotuser',
			'email' => 'pivot@example.com'
		]);

		// Create category and tag
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Pivot Category', 'pivot-category', $now, $now] );
		$categoryId = (int)$this->pdo->lastInsertId();

		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Pivot Tag', 'pivot-tag', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Pivot Post', 'pivot-post', 'Content', '{"blocks":[]}', $userId, 'published', $now, $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach category and tag to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)"
		);
		$stmt->execute( [$postId, $categoryId] );

		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)"
		);
		$stmt->execute( [$postId, $tagId] );

		// Verify pivot entries exist
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_categories WHERE post_id = ?" );
		$stmt->execute( [$postId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'] );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_tags WHERE post_id = ?" );
		$stmt->execute( [$postId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'] );

		// Delete post
		$stmt = $this->pdo->prepare( "DELETE FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );

		// Verify both pivot entries are removed
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_categories WHERE post_id = ?" );
		$stmt->execute( [$postId] );
		$count = $stmt->fetch();
		$this->assertEquals( 0, $count['count'], 'Category pivot entry should be deleted' );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM post_tags WHERE post_id = ?" );
		$stmt->execute( [$postId] );
		$count = $stmt->fetch();
		$this->assertEquals( 0, $count['count'], 'Tag pivot entry should be deleted' );

		// Verify category and tag still exist
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM categories WHERE id = ?" );
		$stmt->execute( [$categoryId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'], 'Category should still exist' );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM tags WHERE id = ?" );
		$stmt->execute( [$tagId] );
		$count = $stmt->fetch();
		$this->assertEquals( 1, $count['count'], 'Tag should still exist' );
	}

	/**
	 * Test event category deletion sets category_id to NULL on events
	 */
	public function testEventCategoryDeletionNullifiesEvents(): void
	{
		// Create event category
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO event_categories (name, slug, color, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Event Category', 'test-event-category', '#3b82f6', $now, $now] );
		$categoryId = (int)$this->pdo->lastInsertId();

		// Create event with this category
		$startDate = date( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
		$stmt = $this->pdo->prepare(
			"INSERT INTO events (title, slug, description, content_raw, start_date, category_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Categorized Event', 'categorized-event', 'Description', '{"blocks":[]}', $startDate, $categoryId, 'published', $now, $now] );
		$eventId = (int)$this->pdo->lastInsertId();

		// Verify event has category
		$stmt = $this->pdo->prepare( "SELECT category_id FROM events WHERE id = ?" );
		$stmt->execute( [$eventId] );
		$event = $stmt->fetch();
		$this->assertEquals( $categoryId, $event['category_id'] );

		// Delete event category
		$stmt = $this->pdo->prepare( "DELETE FROM event_categories WHERE id = ?" );
		$stmt->execute( [$categoryId] );

		// Verify event still exists but category_id is NULL
		$stmt = $this->pdo->prepare( "SELECT * FROM events WHERE id = ?" );
		$stmt->execute( [$eventId] );
		$event = $stmt->fetch();
		$this->assertNotFalse( $event, 'Event should still exist' );
		$this->assertNull( $event['category_id'], 'Category ID should be NULL after event category deletion' );
		$this->assertEquals( 'Categorized Event', $event['title'] );
	}

	/**
	 * Test user deletion with multiple related records
	 */
	public function testUserDeletionWithMultipleRelatedRecords(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'multiuser',
			'email' => 'multi@example.com'
		]);

		// Create multiple posts, pages, and events
		$now = date( 'Y-m-d H:i:s' );
		$startDate = date( 'Y-m-d H:i:s', strtotime( '+1 week' ) );

		// Create 3 posts
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt->execute( ["Post $i", "multi-post-$i", 'Content', '{"blocks":[]}', $userId, 'published', $now, $now] );
		}

		// Create 2 pages
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, template, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);
		for( $i = 1; $i <= 2; $i++ )
		{
			$stmt->execute( ["Page $i", "multi-page-$i", '{"blocks":[]}', $userId, 'published', 'default', $now, $now] );
		}

		// Create 2 events
		$stmt = $this->pdo->prepare(
			"INSERT INTO events (title, slug, description, content_raw, start_date, created_by, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);
		for( $i = 1; $i <= 2; $i++ )
		{
			$stmt->execute( ["Event $i", "multi-event-$i", 'Description', '{"blocks":[]}', $startDate, $userId, 'published', $now, $now] );
		}

		// Verify counts before deletion
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM posts WHERE author_id = ?" );
		$stmt->execute( [$userId] );
		$count = $stmt->fetch();
		$this->assertEquals( 3, $count['count'] );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM pages WHERE author_id = ?" );
		$stmt->execute( [$userId] );
		$count = $stmt->fetch();
		$this->assertEquals( 2, $count['count'] );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM events WHERE created_by = ?" );
		$stmt->execute( [$userId] );
		$count = $stmt->fetch();
		$this->assertEquals( 2, $count['count'] );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify all records still exist with NULL foreign keys
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM posts WHERE author_id IS NULL" );
		$stmt->execute();
		$count = $stmt->fetch();
		$this->assertGreaterThanOrEqual( 3, $count['count'], 'At least 3 posts should have NULL author_id' );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM pages WHERE author_id IS NULL" );
		$stmt->execute();
		$count = $stmt->fetch();
		$this->assertGreaterThanOrEqual( 2, $count['count'], 'At least 2 pages should have NULL author_id' );

		$stmt = $this->pdo->prepare( "SELECT COUNT(*) as count FROM events WHERE created_by IS NULL" );
		$stmt->execute();
		$count = $stmt->fetch();
		$this->assertGreaterThanOrEqual( 2, $count['count'], 'At least 2 events should have NULL created_by' );
	}
}
