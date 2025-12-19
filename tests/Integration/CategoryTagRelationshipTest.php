<?php

namespace Tests\Integration;

/**
 * Integration test for category and tag relationships.
 *
 * Tests many-to-many relationships between:
 * - Posts and Categories
 * - Posts and Tags
 *
 * Uses real database with actual migrations to test:
 * - Pivot table functionality
 * - Relationship queries
 * - Cascade deletes
 * - Unique constraints
 *
 * @package Tests\Integration
 */
class CategoryTagRelationshipTest extends IntegrationTestCase
{
	/**
	 * Test creating categories and querying them
	 */
	public function testCategoryCreationAndRetrieval(): void
	{
		$now = date( 'Y-m-d H:i:s' );

		// Create parent category
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, description, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);

		$stmt->execute( ['Technology', 'technology', 'Tech articles', $now, $now] );
		$parentId = (int)$this->pdo->lastInsertId();

		// Create child category
		$stmt->execute( ['PHP', 'php', 'PHP programming', $now, $now] );
		$childId = (int)$this->pdo->lastInsertId();

		// Verify categories exist
		$stmt = $this->pdo->prepare( "SELECT * FROM categories ORDER BY name" );
		$stmt->execute();
		$categories = $stmt->fetchAll();

		$this->assertCount( 2, $categories );
		$this->assertEquals( 'PHP', $categories[0]['name'] );
		$this->assertEquals( 'Technology', $categories[1]['name'] );
	}

	/**
	 * Test category slug uniqueness
	 */
	public function testCategorySlugUniqueness(): void
	{
		$now = date( 'Y-m-d H:i:s' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['Category 1', 'unique-slug', $now, $now] );

		// Try to create another category with same slug
		$this->expectException( \PDOException::class );

		$stmt->execute( ['Category 2', 'unique-slug', $now, $now] );
	}

	/**
	 * Test attaching multiple categories to a post
	 */
	public function testPostMultipleCategoryAttachment(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'catuser',
			'email' => 'cat@example.com'
		]);

		// Create categories
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['Web Development', 'web-dev', $now, $now] );
		$webDevId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['Backend', 'backend', $now, $now] );
		$backendId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['Frontend', 'frontend', $now, $now] );
		$frontendId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Full Stack Development',
			'full-stack-dev',
			'Learn full stack',
			'{"blocks":[]}',
			$userId,
			'published',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Attach categories to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at)
			VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $webDevId, $now] );
		$stmt->execute( [$postId, $backendId, $now] );
		$stmt->execute( [$postId, $frontendId, $now] );

		// Query post categories
		$stmt = $this->pdo->prepare(
			"SELECT c.* FROM categories c
			INNER JOIN post_categories pc ON c.id = pc.category_id
			WHERE pc.post_id = ?
			ORDER BY c.name"
		);

		$stmt->execute( [$postId] );
		$postCategories = $stmt->fetchAll();

		$this->assertCount( 3, $postCategories );
		$this->assertEquals( 'Backend', $postCategories[0]['name'] );
		$this->assertEquals( 'Frontend', $postCategories[1]['name'] );
		$this->assertEquals( 'Web Development', $postCategories[2]['name'] );
	}

	/**
	 * Test querying posts by category
	 */
	public function testQueryPostsByCategory(): void
	{
		$userId = $this->createTestUser([
			'username' => 'postcat',
			'email' => 'postcat@example.com'
		]);

		// Create category
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['PHP', 'php', $now, $now] );
		$phpCategoryId = (int)$this->pdo->lastInsertId();

		// Create posts in PHP category
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$postIds = [];
		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt->execute([
				"PHP Article {$i}",
				"php-article-{$i}",
				"Content {$i}",
				'{"blocks":[]}',
				$userId,
				'published',
				$now
			]);
			$postIds[] = (int)$this->pdo->lastInsertId();
		}

		// Attach posts to category
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at)
			VALUES (?, ?, ?)"
		);

		foreach( $postIds as $postId )
		{
			$stmt->execute( [$postId, $phpCategoryId, $now] );
		}

		// Query posts by category
		$stmt = $this->pdo->prepare(
			"SELECT p.* FROM posts p
			INNER JOIN post_categories pc ON p.id = pc.post_id
			WHERE pc.category_id = ?
			ORDER BY p.title"
		);

		$stmt->execute( [$phpCategoryId] );
		$categoryPosts = $stmt->fetchAll();

		$this->assertCount( 3, $categoryPosts );
		$this->assertEquals( 'PHP Article 1', $categoryPosts[0]['title'] );
		$this->assertEquals( 'PHP Article 2', $categoryPosts[1]['title'] );
		$this->assertEquals( 'PHP Article 3', $categoryPosts[2]['title'] );
	}

	/**
	 * Test creating and managing tags
	 */
	public function testTagCreationAndRetrieval(): void
	{
		$now = date( 'Y-m-d H:i:s' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$tagNames = ['tutorial', 'beginner', 'advanced', 'guide'];

		foreach( $tagNames as $tag )
		{
			$stmt->execute( [$tag, $tag, $now, $now] );
		}

		// Verify all tags created
		$stmt = $this->pdo->prepare( "SELECT * FROM tags ORDER BY name" );
		$stmt->execute();
		$tags = $stmt->fetchAll();

		$this->assertCount( 4, $tags );
		$this->assertEquals( 'advanced', $tags[0]['name'] );
		$this->assertEquals( 'beginner', $tags[1]['name'] );
		$this->assertEquals( 'guide', $tags[2]['name'] );
		$this->assertEquals( 'tutorial', $tags[3]['name'] );
	}

	/**
	 * Test tag slug uniqueness
	 */
	public function testTagSlugUniqueness(): void
	{
		$now = date( 'Y-m-d H:i:s' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['Tutorial', 'tutorial-tag', $now, $now] );

		// Try to create another tag with same slug
		$this->expectException( \PDOException::class );

		$stmt->execute( ['Another Tutorial', 'tutorial-tag', $now, $now] );
	}

	/**
	 * Test attaching multiple tags to a post
	 */
	public function testPostMultipleTagAttachment(): void
	{
		$userId = $this->createTestUser([
			'username' => 'taguser',
			'email' => 'tag@example.com'
		]);

		// Create tags
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['php', 'php', $now, $now] );
		$phpTagId = (int)$this->pdo->lastInsertId();

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
			'PHP Tutorial for Beginners',
			'php-tutorial-beginners',
			'Learn PHP basics',
			'{"blocks":[]}',
			$userId,
			'published',
			$now
		]);

		$postId = (int)$this->pdo->lastInsertId();

		// Attach tags to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);

		$stmt->execute( [$postId, $phpTagId, $now] );
		$stmt->execute( [$postId, $tutorialTagId, $now] );
		$stmt->execute( [$postId, $beginnerTagId, $now] );

		// Query post tags
		$stmt = $this->pdo->prepare(
			"SELECT t.* FROM tags t
			INNER JOIN post_tags pt ON t.id = pt.tag_id
			WHERE pt.post_id = ?
			ORDER BY t.name"
		);

		$stmt->execute( [$postId] );
		$postTags = $stmt->fetchAll();

		$this->assertCount( 3, $postTags );
		$this->assertEquals( 'beginner', $postTags[0]['name'] );
		$this->assertEquals( 'php', $postTags[1]['name'] );
		$this->assertEquals( 'tutorial', $postTags[2]['name'] );
	}

	/**
	 * Test querying posts by tag
	 */
	public function testQueryPostsByTag(): void
	{
		$userId = $this->createTestUser([
			'username' => 'posttag',
			'email' => 'posttag@example.com'
		]);

		// Create tag
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute( ['laravel', 'laravel', $now, $now] );
		$laravelTagId = (int)$this->pdo->lastInsertId();

		// Create posts with Laravel tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$postIds = [];
		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt->execute([
				"Laravel Tutorial {$i}",
				"laravel-tutorial-{$i}",
				"Content {$i}",
				'{"blocks":[]}',
				$userId,
				'published',
				$now
			]);
			$postIds[] = (int)$this->pdo->lastInsertId();
		}

		// Attach posts to tag
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);

		foreach( $postIds as $postId )
		{
			$stmt->execute( [$postId, $laravelTagId, $now] );
		}

		// Query posts by tag
		$stmt = $this->pdo->prepare(
			"SELECT p.* FROM posts p
			INNER JOIN post_tags pt ON p.id = pt.post_id
			WHERE pt.tag_id = ?
			ORDER BY p.title"
		);

		$stmt->execute( [$laravelTagId] );
		$tagPosts = $stmt->fetchAll();

		$this->assertCount( 3, $tagPosts );
		$this->assertEquals( 'Laravel Tutorial 1', $tagPosts[0]['title'] );
		$this->assertEquals( 'Laravel Tutorial 2', $tagPosts[1]['title'] );
		$this->assertEquals( 'Laravel Tutorial 3', $tagPosts[2]['title'] );
	}

	/**
	 * Test removing category detaches from posts
	 */
	public function testCategoryDeletionDetachesFromPosts(): void
	{
		$userId = $this->createTestUser([
			'username' => 'detachcat',
			'email' => 'detachcat@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create category
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Temporary', 'temporary', $now, $now] );
		$categoryId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute( ['Test Post', 'test-post', 'Content', '{"blocks":[]}', $userId, 'draft', $now] );
		$postId = (int)$this->pdo->lastInsertId();

		// Attach category to post
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at)
			VALUES (?, ?, ?)"
		);
		$stmt->execute( [$postId, $categoryId, $now] );

		// Verify attachment exists
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_categories WHERE post_id = ? AND category_id = ?"
		);
		$stmt->execute( [$postId, $categoryId] );
		$this->assertEquals( 1, (int)$stmt->fetchColumn() );

		// Delete category
		$stmt = $this->pdo->prepare( "DELETE FROM categories WHERE id = ?" );
		$stmt->execute( [$categoryId] );

		// Verify pivot entry was also deleted (cascade or manual cleanup)
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_categories WHERE category_id = ?"
		);
		$stmt->execute( [$categoryId] );
		$this->assertEquals( 0, (int)$stmt->fetchColumn(), 'Category deletion should remove pivot entries' );
	}

	/**
	 * Test post with both categories and tags
	 */
	public function testPostWithCategoriesAndTags(): void
	{
		$userId = $this->createTestUser([
			'username' => 'fullpost',
			'email' => 'fullpost@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );

		// Create categories
		$stmt = $this->pdo->prepare(
			"INSERT INTO categories (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['Programming', 'programming', $now, $now] );
		$progCatId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['Web', 'web', $now, $now] );
		$webCatId = (int)$this->pdo->lastInsertId();

		// Create tags
		$stmt = $this->pdo->prepare(
			"INSERT INTO tags (name, slug, created_at, updated_at)
			VALUES (?, ?, ?, ?)"
		);
		$stmt->execute( ['vue', 'vue', $now, $now] );
		$vueTagId = (int)$this->pdo->lastInsertId();

		$stmt->execute( ['javascript', 'javascript', $now, $now] );
		$jsTagId = (int)$this->pdo->lastInsertId();

		// Create post
		$stmt = $this->pdo->prepare(
			"INSERT INTO posts (title, slug, body, content_raw, author_id, status, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);
		$stmt->execute([
			'Vue.js Web Development',
			'vue-web-dev',
			'Learn Vue',
			'{"blocks":[]}',
			$userId,
			'published',
			$now
		]);
		$postId = (int)$this->pdo->lastInsertId();

		// Attach categories
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_categories (post_id, category_id, created_at)
			VALUES (?, ?, ?)"
		);
		$stmt->execute( [$postId, $progCatId, $now] );
		$stmt->execute( [$postId, $webCatId, $now] );

		// Attach tags
		$stmt = $this->pdo->prepare(
			"INSERT INTO post_tags (post_id, tag_id, created_at)
			VALUES (?, ?, ?)"
		);
		$stmt->execute( [$postId, $vueTagId, $now] );
		$stmt->execute( [$postId, $jsTagId, $now] );

		// Verify post has categories
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_categories WHERE post_id = ?"
		);
		$stmt->execute( [$postId] );
		$this->assertEquals( 2, (int)$stmt->fetchColumn() );

		// Verify post has tags
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM post_tags WHERE post_id = ?"
		);
		$stmt->execute( [$postId] );
		$this->assertEquals( 2, (int)$stmt->fetchColumn() );
	}
}
