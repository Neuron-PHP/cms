<?php

namespace Tests\Integration;

use DateTimeImmutable;

/**
 * Integration test for post publishing and scheduling.
 *
 * Tests:
 * - Draft → Published status transitions
 * - Post scheduling for future publication
 * - Unpublishing (published → draft)
 * - View count tracking
 * - Published date handling
 * - Status-based queries
 *
 * Uses real database with actual migrations.
 *
 * @package Tests\Integration
 */
class PostPublishingSchedulingTest extends IntegrationTestCase
{
	/**
	 * Test publishing a draft post
	 */
	public function testPublishDraftPost(): void
	{
		$userId = $this->createTestUser([
			'username' => 'publisher',
			'email' => 'pub@example.com'
		]);

		// Create draft post
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Draft Article',
			'draft-article',
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Verify it's draft without published date
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'draft', $post['status'] );
		$this->assertNull( $post['published_at'] );

		// Publish the post
		$publishedAt = new DateTimeImmutable();
		$stmt = $this->pdo->prepare(
			"UPDATE posts SET status = ?, published_at = ? WHERE id = ?"
		);

		$stmt->execute([
			'published',
			$publishedAt->format( 'Y-m-d H:i:s' ),
			$postId
		]);

		// Verify published
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'published', $post['status'] );
		$this->assertNotNull( $post['published_at'] );
		$this->assertGreaterThanOrEqual( $now, $post['published_at'] );
	}

	/**
	 * Test unpublishing a post (revert to draft)
	 */
	public function testUnpublishPost(): void
	{
		$userId = $this->createTestUser([
			'username' => 'unpublisher',
			'email' => 'unpub@example.com'
		]);

		// Create published post
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Published Article',
			'published-article-unpub',
			'Content',
			'{"blocks":[]}',
			$userId,
			'published',
			$now,
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Verify it's published
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'published', $post['status'] );
		$this->assertNotNull( $post['published_at'] );

		// Unpublish (revert to draft)
		$stmt = $this->pdo->prepare(
			"UPDATE posts SET status = ?, published_at = NULL WHERE id = ?"
		);

		$stmt->execute( ['draft', $postId] );

		// Verify unpublished
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'draft', $post['status'] );
		$this->assertNull( $post['published_at'] );
	}

	/**
	 * Test scheduling a post for future publication
	 */
	public function testSchedulePostForFuturePublication(): void
	{
		$userId = $this->createTestUser([
			'username' => 'scheduler',
			'email' => 'sched@example.com'
		]);

		// Create draft post
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Future Article',
			'future-article',
			'Content',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Schedule for 1 hour in the future
		$scheduledTime = (new DateTimeImmutable())->modify( '+1 hour' );

		$stmt = $this->pdo->prepare(
			"UPDATE posts SET status = ?, published_at = ? WHERE id = ?"
		);

		$stmt->execute([
			'scheduled',
			$scheduledTime->format( 'Y-m-d H:i:s' ),
			$postId
		]);

		// Verify scheduled
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$post = $stmt->fetch();

		$this->assertEquals( 'scheduled', $post['status'] );
		$this->assertNotNull( $post['published_at'] );
		$this->assertGreaterThan( date( 'Y-m-d H:i:s' ), $post['published_at'] );
	}

	/**
	 * Test finding scheduled posts that should be published
	 */
	public function testFindScheduledPostsReadyToPublish(): void
	{
		$userId = $this->createTestUser([
			'username' => 'autopub',
			'email' => 'autopub@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		// Create scheduled post in the past (should be published)
		$pastTime = (new DateTimeImmutable())->modify( '-1 hour' );
		$stmt->execute([
			'Past Scheduled',
			'past-scheduled',
			'Content',
			'{"blocks":[]}',
			$userId,
			'scheduled',
			$pastTime->format( 'Y-m-d H:i:s' ),
			$now
		]);

		$pastPostId = (int)$this->pdo->lastInsertId();

		// Create scheduled post in the future (should NOT be published yet)
		$futureTime = (new DateTimeImmutable())->modify( '+1 hour' );
		$stmt->execute([
			'Future Scheduled',
			'future-scheduled',
			'Content',
			'{"blocks":[]}',
			$userId,
			'scheduled',
			$futureTime->format( 'Y-m-d H:i:s' ),
			$now
		]);

		$futurePostId = (int)$this->pdo->lastInsertId();

		// Find scheduled posts ready to publish
		$stmt = $this->pdo->prepare(
			"SELECT id, title FROM posts
			WHERE status = ? AND published_at <= ?
			ORDER BY published_at"
		);

		$stmt->execute( ['scheduled', date( 'Y-m-d H:i:s' )] );
		$readyPosts = $stmt->fetchAll();

		$this->assertCount( 1, $readyPosts );
		$this->assertEquals( $pastPostId, $readyPosts[0]['id'] );
		$this->assertEquals( 'Past Scheduled', $readyPosts[0]['title'] );

		// Auto-publish the ready post
		$stmt = $this->pdo->prepare(
			"UPDATE posts SET status = ? WHERE id = ?"
		);

		$stmt->execute( ['published', $pastPostId] );

		// Verify it's now published
		$stmt = $this->pdo->prepare( "SELECT status FROM posts WHERE id = ?" );
		$stmt->execute( [$pastPostId] );
		$status = $stmt->fetchColumn();

		$this->assertEquals( 'published', $status );

		// Verify future post is still scheduled
		$stmt->execute( [$futurePostId] );
		$status = $stmt->fetchColumn();

		$this->assertEquals( 'scheduled', $status );
	}

	/**
	 * Test view count tracking
	 */
	public function testPostViewCountIncrement(): void
	{
		$userId = $this->createTestUser([
			'username' => 'viewtracker',
			'email' => 'viewtrack@example.com'
		]);

		// Create published post with initial view count
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, view_count, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Popular Post',
			'popular-post-views',
			'Content',
			'{"blocks":[]}',
			$userId,
			'published',
			0,
			$now,
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Simulate 10 page views
		for( $i = 1; $i <= 10; $i++ )
		{
			$stmt = $this->pdo->prepare(
				"UPDATE posts SET view_count = view_count + 1 WHERE id = ?"
			);
			$stmt->execute( [$postId] );
		}

		// Verify view count
		$stmt = $this->pdo->prepare( "SELECT view_count FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$viewCount = (int)$stmt->fetchColumn();

		$this->assertEquals( 10, $viewCount );
	}

	/**
	 * Test querying posts by status
	 */
	public function testQueryPostsByStatus(): void
	{
		$userId = $this->createTestUser([
			'username' => 'statusquery',
			'email' => 'statusq@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		// Create posts with different statuses
		$statuses = [
			'draft' => 3,
			'published' => 5,
			'scheduled' => 2
		];

		foreach( $statuses as $status => $count )
		{
			for( $i = 1; $i <= $count; $i++ )
			{
				$publishedAt = in_array( $status, ['published', 'scheduled'] ) ? $now : null;

				$stmt->execute([
					"{$status} Post {$i}",
					"{$status}-post-{$i}",
					'Content',
					'{"blocks":[]}',
					$userId,
					$status,
					$publishedAt,
					$now
				]);
			}
		}

		// Query each status
		foreach( $statuses as $status => $expectedCount )
		{
			$stmt = $this->pdo->prepare(
				"SELECT COUNT(*) FROM posts WHERE author_id = ? AND status = ?"
			);
			$stmt->execute( [$userId, $status] );
			$count = (int)$stmt->fetchColumn();

			$this->assertEquals( $expectedCount, $count, "Should have {$expectedCount} {$status} posts" );
		}
	}

	/**
	 * Test most viewed posts query
	 */
	public function testMostViewedPostsQuery(): void
	{
		$userId = $this->createTestUser([
			'username' => 'viewranking',
			'email' => 'viewrank@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, view_count, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		// Create posts with different view counts
		$viewCounts = [100, 500, 250, 750, 50];

		foreach( $viewCounts as $index => $views )
		{
			$stmt->execute([
				"Post with {$views} views",
				"post-views-{$index}",
				'Content',
				'{"blocks":[]}',
				$userId,
				'published',
				$views,
				$now,
				$now
			]);
		}

		// Query top 3 most viewed posts
		$stmt = $this->pdo->prepare(
			"SELECT title, view_count FROM posts
			WHERE author_id = ? AND status = ?
			ORDER BY view_count DESC
			LIMIT 3"
		);

		$stmt->execute( [$userId, 'published'] );
		$topPosts = $stmt->fetchAll();

		$this->assertCount( 3, $topPosts );
		$this->assertEquals( 750, $topPosts[0]['view_count'] );
		$this->assertEquals( 500, $topPosts[1]['view_count'] );
		$this->assertEquals( 250, $topPosts[2]['view_count'] );
	}

	/**
	 * Test recently published posts query
	 */
	public function testRecentlyPublishedPostsQuery(): void
	{
		$userId = $this->createTestUser([
			'username' => 'recency',
			'email' => 'recent@example.com'
		]);

		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		// Create posts published at different times
		$now = new DateTimeImmutable();

		for( $i = 5; $i >= 1; $i-- )
		{
			$publishedAt = $now->modify( "-{$i} days" );

			$stmt->execute([
				"Post from {$i} days ago",
				"post-day-{$i}",
				'Content',
				'{"blocks":[]}',
				$userId,
				'published',
				$publishedAt->format( 'Y-m-d H:i:s' ),
				$publishedAt->format( 'Y-m-d H:i:s' )
			]);
		}

		// Query posts published in last 3 days
		$threeDaysAgo = $now->modify( '-3 days' );

		$stmt = $this->pdo->prepare(
			"SELECT title FROM posts
			WHERE author_id = ? AND status = ? AND published_at >= ?
			ORDER BY published_at DESC"
		);

		$stmt->execute([
			$userId,
			'published',
			$threeDaysAgo->format( 'Y-m-d H:i:s' )
		]);

		$recentPosts = $stmt->fetchAll();

		$this->assertCount( 3, $recentPosts );
		$this->assertEquals( 'Post from 1 days ago', $recentPosts[0]['title'] );
		$this->assertEquals( 'Post from 2 days ago', $recentPosts[1]['title'] );
		$this->assertEquals( 'Post from 3 days ago', $recentPosts[2]['title'] );
	}

	/**
	 * Test published date preservation on updates
	 */
	public function testPublishedDatePreservedOnUpdate(): void
	{
		$userId = $this->createTestUser([
			'username' => 'datekeeper',
			'email' => 'datekeeper@example.com'
		]);

		// Create published post
		$originalPublishDate = (new DateTimeImmutable())->modify( '-7 days' );
		$now = date( 'Y-m-d H:i:s' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, published_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Original Title',
			'date-preservation',
			'Original content',
			'{"blocks":[]}',
			$userId,
			'published',
			$originalPublishDate->format( 'Y-m-d H:i:s' ),
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Update post content (but keep published status)
		$stmt = $this->pdo->prepare(
			"UPDATE posts SET title = ?, body = ?, updated_at = ? WHERE id = ?"
		);

		$stmt->execute([
			'Updated Title',
			'Updated content',
			date( 'Y-m-d H:i:s' ),
			$postId
		]);

		// Verify published_at was NOT changed
		$stmt = $this->pdo->prepare( "SELECT published_at FROM posts WHERE id = ?" );
		$stmt->execute( [$postId] );
		$publishedAt = $stmt->fetchColumn();

		$this->assertEquals(
			$originalPublishDate->format( 'Y-m-d H:i:s' ),
			$publishedAt,
			'Published date should be preserved on update'
		);
	}
}
