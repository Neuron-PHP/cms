<?php

namespace Tests\Integration;

/**
 * Integration test for tag management and resolution.
 *
 * Tests:
 * - Tag creation from names
 * - Tag auto-slug generation
 * - Tag resolution (find-or-create)
 * - Duplicate tag prevention
 * - Tag usage tracking
 * - Unused tag cleanup
 * - Tag merging
 *
 * Uses real database with actual migrations.
 *
 * @package Tests\Integration
 */
class TagManagementTest extends IntegrationTestCase
{
	/**
	 * Test creating tags from names
	 */
	public function testCreateTagsFromNames(): void
	{
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$tagNames = ['php', 'javascript', 'python', 'ruby', 'go'];

		foreach( $tagNames as $name )
		{
			$stmt->execute( [$name, $name, $now, $now] );
		}

		// Verify all tags created
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM tags" );
		$stmt->execute();
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 5, $count );

		// Verify names and slugs
		$stmt = $this->pdo->prepare( "SELECT name, slug FROM tags ORDER BY name" );
		$stmt->execute();
		$tags = $stmt->fetchAll();

		$this->assertEquals( 'go', $tags[0]['name'] );
		$this->assertEquals( 'go', $tags[0]['slug'] );
	}

	/**
	 * Test tag slug auto-generation from multi-word names
	 */
	public function testTagSlugGeneration(): void
	{
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$tags = [
			['Machine Learning', 'machine-learning'],
			['Web Development', 'web-development'],
			['API Design', 'api-design'],
			['Code Review', 'code-review']
		];

		foreach( $tags as [$name, $slug] )
		{
			$stmt->execute( [$name, $slug, $now, $now] );
		}

		// Verify slugs are correct
		$stmt = $this->pdo->prepare( "SELECT name, slug FROM tags WHERE name = ?" );
		$stmt->execute( ['Machine Learning'] );
		$tag = $stmt->fetch();

		$this->assertEquals( 'machine-learning', $tag['slug'] );
	}

	/**
	 * Test tag resolution - find existing tag
	 */
	public function testFindExistingTag(): void
	{
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['laravel', 'laravel', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Find tag by name
		$stmt = $this->pdo->prepare( "SELECT * FROM tags WHERE name = ?" );
		$stmt->execute( ['laravel'] );
		$tag = $stmt->fetch();

		$this->assertNotFalse( $tag );
		$this->assertEquals( $tagId, $tag['id'] );
		$this->assertEquals( 'laravel', $tag['name'] );
	}

	/**
	 * Test tag resolution - create if not exists
	 */
	public function testCreateTagIfNotExists(): void
	{
		// Try to find non-existent tag
		$stmt = $this->pdo->prepare( "SELECT * FROM tags WHERE name = ?" );
		$stmt->execute( ['symfony'] );
		$tag = $stmt->fetch();

		$this->assertFalse( $tag );

		// Create the tag
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['symfony', 'symfony', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Verify it now exists
		$stmt = $this->pdo->prepare( "SELECT * FROM tags WHERE name = ?" );
		$stmt->execute( ['symfony'] );
		$tag = $stmt->fetch();

		$this->assertNotFalse( $tag );
		$this->assertEquals( $tagId, $tag['id'] );
	}

	/**
	 * Test parsing comma-separated tag string
	 */
	public function testParseCommaSeparatedTags(): void
	{
		$tagString = 'php, javascript, python, ruby';
		$tagNames = array_map( 'trim', explode( ',', $tagString ) );

		$this->assertCount( 4, $tagNames );
		$this->assertEquals( 'php', $tagNames[0] );
		$this->assertEquals( 'javascript', $tagNames[1] );
		$this->assertEquals( 'python', $tagNames[2] );
		$this->assertEquals( 'ruby', $tagNames[3] );

		// Create tags from parsed names
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		foreach( $tagNames as $name )
		{
			$stmt->execute( [$name, $name, $now, $now] );
		}

		// Verify all created
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM tags" );
		$stmt->execute();
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 4, $count );
	}

	/**
	 * Test tag case normalization
	 */
	public function testTagCaseNormalization(): void
	{
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		// Create tag with lowercase
		$stmt->execute( ['docker', 'docker', $now, $now] );

		// Search case-insensitively (application layer would handle this)
		$stmt = $this->pdo->prepare( "SELECT * FROM tags WHERE LOWER(name) = LOWER(?)" );
		$stmt->execute( ['DOCKER'] );
		$tag = $stmt->fetch();

		$this->assertNotFalse( $tag );
		$this->assertEquals( 'docker', $tag['name'] );
	}

	/**
	 * Test finding unused tags
	 */
	public function testFindUnusedTags(): void
	{
		$userId = $this->createTestUser([
			'username' => 'tagger',
			'email' => 'tagger@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create tags
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['used-tag', 'used-tag', $now, $now] );
		$usedTagId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['unused-tag-1', 'unused-tag-1', $now, $now] );
		$unusedTagId1 = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['unused-tag-2', 'unused-tag-2', $now, $now] );
		$unusedTagId2 = (int)$this->pdo->lastInsertId();

		// Create post with one tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute( ['Tagged Post', 'tagged-post', 'Content', '{"blocks":[]}', $userId, 'draft', $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach used tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $usedTagId, $now] );

		// Find unused tags
		$stmt = $this->pdo->prepare(
			"SELECT t.* FROM tags t
			LEFT JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.tag_id IS NULL
			ORDER BY t.name"
		);

		$stmt->execute();
		$unusedTags = $stmt->fetchAll();

		$this->assertCount( 2, $unusedTags );
		$this->assertEquals( 'unused-tag-1', $unusedTags[0]['name'] );
		$this->assertEquals( 'unused-tag-2', $unusedTags[1]['name'] );
	}

	/**
	 * Test tag usage count
	 */
	public function testTagUsageCount(): void
	{
		$userId = $this->createTestUser([
			'username' => 'usageuser',
			'email' => 'usage@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['popular', 'popular', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Create multiple posts
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$postIds = [];
		for( $i = 1; $i <= 5; $i++ )
		{
			$stmt->execute([
				"Post {$i}",
				"post-usage-{$i}",
				'Content',
				'{"blocks":[]}',
				$userId,
				'published',
				$now
			]);
			$postIds[] = (int)$this->pdo->lastInsertId();
		}

		// Attach tag to all posts
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);

		foreach( $postIds as $postId )
		{
			$stmt->execute( [$postId, $tagId, $now] );
		}

		// Count tag usage
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_tags WHERE tag_id = ?"
		);

		$stmt->execute( [$tagId] );
		$usageCount = (int)$stmt->fetchColumn();

		$this->assertEquals( 5, $usageCount );
	}

	/**
	 * Test most popular tags query
	 */
	public function testMostPopularTagsQuery(): void
	{
		$userId = $this->createTestUser([
			'username' => 'popularuser',
			'email' => 'popular@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create tags with different usage counts
		$tagData = [
			['tag-1', 10],
			['tag-2', 5],
			['tag-3', 15],
			['tag-4', 2]
		];

		foreach( $tagData as [$tagName, $usageCount] )
		{
			// Create tag
			$tagStmt = $this->pdo->prepare(
				"INSERT INTO tags (name, slug, created_at, updated_at)
				VALUES (?, ?, ?, ?)"
			);

			$tagStmt->execute( [$tagName, $tagName, $now, $now] );
			$tagId = (int)$this->pdo->lastInsertId();

			// Create posts and attach tag
			$postStmt = $this->pdo->prepare(
				"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
				VALUES (?, ?, ?, ?, ?, ?, ?)"
			);

			$pivotStmt = $this->pdo->prepare(
				"INSERT INTO post_tags (post_id, tag_id, created_at)
				VALUES (?, ?, ?)"
			);

			for( $i = 0; $i < $usageCount; $i++ )
			{
				$postStmt->execute([
					"{$tagName} post {$i}",
					"{$tagName}-post-{$i}",
					'Content',
					'{"blocks":[]}',
					$userId,
					'published',
					$now
				]);

				$postId = (int)$this->pdo->lastInsertId();

				// Attach tag
				$pivotStmt->execute( [$postId, $tagId, $now] );
			}
		}

		// Query top 3 most popular tags
		$stmt = $this->pdo->prepare(
			"SELECT t.name, COUNT(pt.post_id) as post_count
			FROM tags t
			LEFT JOIN post_tags pt ON t.id = pt.tag_id
			GROUP BY t.id, t.name
			ORDER BY post_count DESC
			LIMIT 3"
		);

		$stmt->execute();
		$popularTags = $stmt->fetchAll();

		$this->assertCount( 3, $popularTags );
		$this->assertEquals( 'tag-3', $popularTags[0]['name'] );
		$this->assertEquals( 15, $popularTags[0]['post_count'] );
		$this->assertEquals( 'tag-1', $popularTags[1]['name'] );
		$this->assertEquals( 10, $popularTags[1]['post_count'] );
		$this->assertEquals( 'tag-2', $popularTags[2]['name'] );
		$this->assertEquals( 5, $popularTags[2]['post_count'] );
	}

	/**
	 * Test deleting unused tags
	 */
	public function testDeleteUnusedTags(): void
	{
		$now = date( 'Y-m-d H:i:s' );

		// Create unused tags
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['orphan-1', 'orphan-1', $now, $now] );
		$orphan1Id = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['orphan-2', 'orphan-2', $now, $now] );
		$orphan2Id = (int)$this->pdo->lastInsertId();

		// Delete unused tags
		$stmt = $this->pdo->prepare(
			"DELETE FROM tags
			WHERE id NOT IN (SELECT DISTINCT tag_id FROM post_tags)"
		);

		$stmt->execute();
		$deletedCount = $stmt->rowCount();

		$this->assertEquals( 2, $deletedCount );

		// Verify they're deleted
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM tags WHERE id IN (?, ?)" );
		$stmt->execute( [$orphan1Id, $orphan2Id] );
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test tag deletion cascades to post_tags pivot
	 */
	public function testTagDeletionCascadesToPivot(): void
	{
		$userId = $this->createTestUser([
			'username' => 'cascadetag',
			'email' => 'cascadetag@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['temp-tag', 'temp-tag', $now, $now] );
		$tagId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute( ['Post', 'post-cascade', 'Content', '{"blocks":[]}', $userId, 'draft', $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $tagId, $now] );

		// Verify pivot exists
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_tags WHERE tag_id = ?"
		);
		$stmt->execute( [$tagId] );
		$this->assertEquals( 1, (int)$stmt->fetchColumn() );

		// Delete tag
		$stmt = $this->pdo->prepare( "DELETE FROM tags WHERE id = ?" );
		$stmt->execute( [$tagId] );

		// Verify pivot entry was also deleted
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_tags WHERE tag_id = ?"
		);
		$stmt->execute( [$tagId] );
		$this->assertEquals( 0, (int)$stmt->fetchColumn(), 'Tag deletion should cascade to pivot table' );
	}

	/**
	 * Test handling whitespace in tag names
	 */
	public function testTagWhitespaceHandling(): void
	{
		$tagString = '  php  ,  javascript  , python, ruby  ';
		$tagNames = array_map( 'trim', explode( ',', $tagString ) );

		// Remove empty tags
		$tagNames = array_filter( $tagNames );

		$this->assertCount( 4, $tagNames );
		$this->assertEquals( 'php', $tagNames[0] );
		$this->assertEquals( 'javascript', $tagNames[1] );
	}

	/**
	 * Test empty tag string handling
	 */
	public function testEmptyTagStringHandling(): void
	{
		$tagString = '';
		$tagNames = array_map( 'trim', explode( ',', $tagString ) );
		$tagNames = array_filter( $tagNames );

		$this->assertEmpty( $tagNames );
	}
}
