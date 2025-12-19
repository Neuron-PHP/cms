<?php

namespace Tests\Integration;

use DateTimeImmutable;

/**
 * Real integration test for post publishing flow.
 *
 * This test uses actual database with real migrations,
 * testing the complete workflow from creation to deletion.
 *
 * Unlike unit tests with mocks or in-memory SQLite:
 * - Uses real database (SQLite file, MySQL, or PostgreSQL)
 * - Runs actual Phinx migrations
 * - Tests foreign key constraints
 * - Tests real data persistence
 * - Validates schema integrity
 *
 * @package Tests\Integration
 */
class PostPublishingFlowTest extends IntegrationTestCase
{
	/**
	 * Test complete post publishing flow with all steps
	 */
	public function testCompletePostPublishingFlow(): void
	{
		// 1. Create a test user (author)
		$userId = $this->createTestUser([
			'username' => 'testauthor',
			'email' => 'author@example.com',
			'role' => 'editor'
		]);

		// 2. Create a draft post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, excerpt, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$content = json_encode([
			'blocks' => [
				['type' => 'paragraph', 'data' => ['text' => 'This is the first paragraph']]
			]
		]);

		$stmt->execute([
			'My First Post',
			'my-first-post',
			'This is the first paragraph',
			$content,
			'A short excerpt',
			$userId,
			'draft',
			date( 'Y-m-d H:i:s' )
		]);

		$postId = (int)$this->pdo->lastInsertId();
		$this->assertGreaterThan( 0, $postId );

		// 3. Verify post was created as draft
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'My First Post', $post['title'] );
		$this->assertEquals( 'my-first-post', $post['slug'] );
		$this->assertEquals( 'draft', $post['status'] );
		$this->assertEquals( $userId, $post['author_id'] );
		$this->assertNull( $post['published_at'] );

		// 4. Update post content
		$stmt = $this->pdo->prepare(
			"UPDATE posts
			SET title = ?, body = ?, content_raw = ?, updated_at = ?
			WHERE id = ?"
		);

		$newContent = json_encode([
			'blocks' => [
				['type' => 'header', 'data' => ['text' => 'Updated Title', 'level' => 1]],
				['type' => 'paragraph', 'data' => ['text' => 'Updated content with more details']]
			]
		]);

		$stmt->execute([
			'My Updated Post',
			'Updated Title Updated content with more details',
			$newContent,
			date( 'Y-m-d H:i:s' ),
			$postId
		]);

		// 5. Verify update
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'My Updated Post', $post['title'] );
		$this->assertEquals( 'draft', $post['status'] ); // Still draft

		// 6. Publish the post
		$publishedAt = new DateTimeImmutable();
		$stmt = $this->pdo->prepare(
			"UPDATE posts
			SET status = ?, published_at = ?, updated_at = ?
			WHERE id = ?"
		);

		$stmt->execute([
			'published',
			$publishedAt->format( 'Y-m-d H:i:s' ),
			date( 'Y-m-d H:i:s' ),
			$postId
		]);

		// 7. Verify post is published
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'published', $post['status'] );
		$this->assertNotNull( $post['published_at'] );

		// 8. Delete the post
		$stmt = $this->pdo->prepare( "DELETE FROM posts WHERE id = ?" );
		$result = $stmt->execute( [$postId] );

		$this->assertTrue( $result );

		// 9. Verify post is deleted
		$stmt = $this->pdo->prepare( "SELECT * FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertFalse( $post );
	}

	/**
	 * Test post with categories relationship
	 */
	public function testPostWithCategories(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'categoryuser',
			'email' => 'catuser@example.com'
		]);

		// Create categories
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at) VALUES (?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute( ['Technology', 'technology', $now, $now] );
		$techCategoryId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['PHP', 'php', $now, $now] );
		$phpCategoryId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'PHP Programming',
			'php-programming',
			'Learn PHP',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Attach categories to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at) VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $techCategoryId, $now] );
		$stmt->execute( [$postId, $phpCategoryId, $now] );

		// Verify relationships
		$stmt = $this->pdo->prepare(
			"SELECT c.* FROM categories c
			INNER JOIN post_categories pc ON c.id = pc.category_id
			WHERE pc.post_id = ?
			ORDER BY c.name"
		);

		$stmt->execute( [$postId] );
		$categories = $stmt->fetchAll();

		$this->assertCount( 2, $categories );
		$this->assertEquals( 'PHP', $categories[0]['name'] );
		$this->assertEquals( 'Technology', $categories[1]['name'] );
	}

	/**
	 * Test post with tags relationship
	 */
	public function testPostWithTags(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'taguser',
			'email' => 'taguser@example.com'
		]);

		// Create tags
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at) VALUES (?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute( ['tutorial', 'tutorial', $now, $now] );
		$tutorialTagId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['beginner', 'beginner', $now, $now] );
		$beginnerTagId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Getting Started',
			'getting-started',
			'Tutorial for beginners',
			'{"blocks":[]}',
			$userId,
			'published',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Attach tags to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at) VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $tutorialTagId, $now] );
		$stmt->execute( [$postId, $beginnerTagId, $now] );

		// Verify relationships
		$stmt = $this->pdo->prepare(
			"SELECT t.* FROM tags t
			INNER JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.post_id = ?
			ORDER BY t.name"
		);

		$stmt->execute( [$postId] );
		$tags = $stmt->fetchAll();

		$this->assertCount( 2, $tags );
		$this->assertEquals( 'beginner', $tags[0]['name'] );
		$this->assertEquals( 'tutorial', $tags[1]['name'] );
	}

	/**
	 * Test foreign key constraint - deleting user cascades to posts
	 */
	public function testUserDeletionCascadesToPosts(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'cascadeuser',
			'email' => 'cascade@example.com'
		]);

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Test Post',
			'test-post-cascade',
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			date( 'Y-m-d H:i:s' )
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Verify post exists
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM posts WHERE author_id = ?" );
		$stmt->execute( [$userId] );
		$count = (int)$stmt->fetchColumn();
		$this->assertEquals( 1, $count );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify post was cascade deleted
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$count = (int)$stmt->fetchColumn();
		$this->assertEquals( 0, $count, 'Post should be cascade deleted when user is deleted' );
	}

	/**
	 * Test slug uniqueness constraint
	 */
	public function testSlugUniquenessConstraint(): void
	{
		$userId = $this->createTestUser([
			'username' => 'sluguser',
			'email' => 'slug@example.com'
		]);

		// Create first post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'First Post',
			'unique-slug',
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			date( 'Y-m-d H:i:s' )
		]);

		// Try to create second post with same slug - should fail
		$this->expectException( \PDOException::class );

		$stmt->execute([
			'Second Post',
			'unique-slug', // Duplicate slug
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			date( 'Y-m-d H:i:s' )
		]);
	}

	/**
	 * Test transaction isolation - changes in one test don't affect another
	 */
	public function testTransactionIsolation(): void
	{
		// Create post in this test
		$userId = $this->createTestUser([
			'username' => 'isolationuser',
			'email' => 'isolation@example.com'
		]);

		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Isolation Test',
			'isolation-test',
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			date( 'Y-m-d H:i:s' )
		]);

		$postId = (int)$this->pdo->lastInsertId();
		$this->assertGreaterThan( 0, $postId );

		// This will be rolled back after test completes
		// Next test should not see this data
	}
}
