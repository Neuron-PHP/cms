<?php

namespace Tests\Integration;

use DateTimeImmutable;

/**
 * Integration test for page management workflow.
 *
 * Tests complete page lifecycle:
 * - Page creation with templates and metadata
 * - Page updates and versioning
 * - Page publishing workflow
 * - View count tracking
 * - SEO metadata
 * - Slug uniqueness
 *
 * Uses real database with actual migrations.
 *
 * @package Tests\Integration
 */
class PageManagementFlowTest extends IntegrationTestCase
{
	/**
	 * Test complete page creation and retrieval
	 */
	public function testPageCreationFlow(): void
	{
		// Create author
		$userId = $this->createTestUser([
			'username' => 'pageauthor',
			'email' => 'pageauthor@example.com'
		]);

		// Create page
		$content = json_encode([
			'blocks' => [
				['type' => 'header', 'data' => ['text' => 'About Us', 'level' => 1]],
				['type' => 'paragraph', 'data' => ['text' => 'We are a company...']]
			]
		]);

		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, template, meta_title, meta_description, meta_keywords, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute([
			'About Us',
			'about-us',
			$content,
			'default',
			'About Our Company',
			'Learn more about our company history and values',
			'about, company, history',
			$userId,
			'draft',
			$now,
			$now
		]);

		$pageId = (int)$this->pdo->lastInsertId();
		$this->assertGreaterThan( 0, $pageId );

		// Verify page was created correctly
		$stmt = $this->pdo->prepare( "SELECT * FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();

		$this->assertEquals( 'About Us', $page['title'] );
		$this->assertEquals( 'about-us', $page['slug'] );
		$this->assertEquals( 'default', $page['template'] );
		$this->assertEquals( 'About Our Company', $page['meta_title'] );
		$this->assertEquals( 'draft', $page['status'] );
		$this->assertEquals( $userId, $page['author_id'] );
		$this->assertNull( $page['published_at'] );
	}

	/**
	 * Test page update flow
	 */
	public function testPageUpdateFlow(): void
	{
		$userId = $this->createTestUser([
			'username' => 'updateuser',
			'email' => 'update@example.com'
		]);

		// Create page
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Original Title',
			'original-slug',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now,
			$now
		]);

		$pageId = (int)$this->pdo->lastInsertId();

		// Update page
		sleep(1); // Ensure updated_at is different
		$updatedContent = json_encode([
			'blocks' => [
				['type' => 'paragraph', 'data' => ['text' => 'Updated content']]
			]
		]);

		$stmt = $this->pdo->prepare(
			"UPDATE pages
			SET title = ?, content = ?, template = ?, meta_title = ?, updated_at = ?
			WHERE id = ?"
		);

		$newNow = date( 'Y-m-d H:i:s' );
		$stmt->execute([
			'Updated Title',
			$updatedContent,
			'custom',
			'Updated Meta Title',
			$newNow,
			$pageId
		]);

		// Verify updates
		$stmt = $this->pdo->prepare( "SELECT * FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();

		$this->assertEquals( 'Updated Title', $page['title'] );
		$this->assertEquals( 'custom', $page['template'] );
		$this->assertEquals( 'Updated Meta Title', $page['meta_title'] );
		$this->assertNotEquals( $now, $page['updated_at'] );
	}

	/**
	 * Test page publishing workflow
	 */
	public function testPagePublishingWorkflow(): void
	{
		$userId = $this->createTestUser([
			'username' => 'publisher',
			'email' => 'publisher@example.com'
		]);

		// Create draft page
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Draft Page',
			'draft-page',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now,
			$now
		]);

		$pageId = (int)$this->pdo->lastInsertId();

		// Verify it's draft
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();

		$this->assertEquals( 'draft', $page['status'] );
		$this->assertNull( $page['published_at'] );

		// Publish page
		$publishedAt = new DateTimeImmutable();
		$stmt = $this->pdo->prepare(
			"UPDATE pages SET status = ?, published_at = ?, updated_at = ? WHERE id = ?"
		);

		$stmt->execute([
			'published',
			$publishedAt->format( 'Y-m-d H:i:s' ),
			date( 'Y-m-d H:i:s' ),
			$pageId
		]);

		// Verify published
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();

		$this->assertEquals( 'published', $page['status'] );
		$this->assertNotNull( $page['published_at'] );

		// Unpublish (revert to draft)
		$stmt = $this->pdo->prepare(
			"UPDATE pages SET status = ?, published_at = NULL WHERE id = ?"
		);

		$stmt->execute( ['draft', $pageId] );

		// Verify unpublished
		$stmt = $this->pdo->prepare( "SELECT status, published_at FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$page = $stmt->fetch();

		$this->assertEquals( 'draft', $page['status'] );
		$this->assertNull( $page['published_at'] );
	}

	/**
	 * Test page slug uniqueness constraint
	 */
	public function testPageSlugUniqueness(): void
	{
		$userId = $this->createTestUser([
			'username' => 'sluguser',
			'email' => 'slug@example.com'
		]);

		// Create first page
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Page One',
			'unique-page-slug',
			'{"blocks":[]}',
			$userId,
			'draft',
			$now,
			$now
		]);

		// Try to create second page with same slug
		$this->expectException( \PDOException::class );

		$stmt->execute([
			'Page Two',
			'unique-page-slug', // Duplicate
			'{"blocks":[]}',
			$userId,
			'draft',
			$now,
			$now
		]);
	}

	/**
	 * Test page templates
	 */
	public function testPageTemplates(): void
	{
		$userId = $this->createTestUser([
			'username' => 'templateuser',
			'email' => 'template@example.com'
		]);

		$templates = ['default', 'full-width', 'sidebar-left', 'sidebar-right', 'landing'];
		$now = date( 'Y-m-d H:i:s' );

		foreach( $templates as $template )
		{
			$stmt = $this->pdo->prepare(
				"INSERT INTO pages (title, slug, content, template, author_id, status, created_at, updated_at)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
			);

			$stmt->execute([
				"Page with {$template}",
				"page-{$template}",
				'{"blocks":[]}',
				$template,
				$userId,
				'draft',
				$now,
				$now
			]);
		}

		// Query pages by template
		$stmt = $this->pdo->prepare( "SELECT title, template FROM pages WHERE author_id = ? ORDER BY template" );
		$stmt->execute( [$userId] );
		$pages = $stmt->fetchAll();

		$this->assertCount( 5, $pages );
		$this->assertEquals( 'default', $pages[0]['template'] );
		$this->assertEquals( 'full-width', $pages[1]['template'] );
	}

	/**
	 * Test page view count tracking
	 */
	public function testPageViewCountTracking(): void
	{
		$userId = $this->createTestUser([
			'username' => 'viewuser',
			'email' => 'view@example.com'
		]);

		// Create page
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, view_count, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'Popular Page',
			'popular-page',
			'{"blocks":[]}',
			$userId,
			'published',
			0,
			$now,
			$now
		]);

		$pageId = (int)$this->pdo->lastInsertId();

		// Simulate 5 page views
		for( $i = 1; $i <= 5; $i++ )
		{
			$stmt = $this->pdo->prepare(
				"UPDATE pages SET view_count = view_count + 1 WHERE id = ?"
			);
			$stmt->execute( [$pageId] );

			// Verify count incremented
			$stmt = $this->pdo->prepare( "SELECT view_count FROM pages WHERE id = ?" );
			$stmt->execute( [$pageId] );
			$count = (int)$stmt->fetchColumn();

			$this->assertEquals( $i, $count );
		}

		// Verify final count
		$stmt = $this->pdo->prepare( "SELECT view_count FROM pages WHERE id = ?" );
		$stmt->execute( [$pageId] );
		$finalCount = (int)$stmt->fetchColumn();

		$this->assertEquals( 5, $finalCount );
	}

	/**
	 * Test SEO metadata fields
	 */
	public function testSeoMetadata(): void
	{
		$userId = $this->createTestUser([
			'username' => 'seouser',
			'email' => 'seo@example.com'
		]);

		// Create page with full SEO metadata
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, meta_title, meta_description, meta_keywords, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$stmt->execute([
			'SEO Optimized Page',
			'seo-page',
			'{"blocks":[]}',
			'Best SEO Page - My Company',
			'This is a comprehensive SEO-optimized page with all metadata fields properly filled out',
			'seo, optimization, metadata, keywords',
			$userId,
			'published',
			$now,
			$now
		]);

		$pageId = (int)$this->pdo->lastInsertId();

		// Verify SEO metadata
		$stmt = $this->pdo->prepare(
			"SELECT meta_title, meta_description, meta_keywords FROM pages WHERE id = ?"
		);
		$stmt->execute( [$pageId] );
		$seo = $stmt->fetch();

		$this->assertEquals( 'Best SEO Page - My Company', $seo['meta_title'] );
		$this->assertStringContainsString( 'comprehensive SEO-optimized', $seo['meta_description'] );
		$this->assertStringContainsString( 'seo', $seo['meta_keywords'] );
		$this->assertStringContainsString( 'optimization', $seo['meta_keywords'] );
	}

	/**
	 * Test user deletion cascades to pages
	 */
	public function testUserDeletionCascadesToPages(): void
	{
		// Create user
		$userId = $this->createTestUser([
			'username' => 'cascadeuser',
			'email' => 'cascade@example.com'
		]);

		// Create pages for user
		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?)"
		);

		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt->execute([
				"Page {$i}",
				"page-{$i}-cascade",
				'{"blocks":[]}',
				$userId,
				'draft',
				$now,
				$now
			]);
		}

		// Verify pages exist
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM pages WHERE author_id = ?" );
		$stmt->execute( [$userId] );
		$count = (int)$stmt->fetchColumn();
		$this->assertEquals( 3, $count );

		// Delete user
		$stmt = $this->pdo->prepare( "DELETE FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );

		// Verify pages were cascade deleted
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM pages WHERE author_id = ?" );
		$stmt->execute( [$userId] );
		$count = (int)$stmt->fetchColumn();
		$this->assertEquals( 0, $count, 'Pages should be cascade deleted when author is deleted' );
	}

	/**
	 * Test querying published pages
	 */
	public function testQueryPublishedPages(): void
	{
		$userId = $this->createTestUser([
			'username' => 'pubuser',
			'email' => 'pub@example.com'
		]);

		$now = date( 'Y-m-d H:i:s' );
		$stmt = $this->pdo->prepare(
			"INSERT INTO pages (title, slug, content, author_id, status, published_at, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		// Create 3 published pages
		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt->execute([
				"Published Page {$i}",
				"published-{$i}",
				'{"blocks":[]}',
				$userId,
				'published',
				$now,
				$now,
				$now
			]);
		}

		// Create 2 draft pages
		for( $i = 1; $i <= 2; $i++ )
		{
			$stmt->execute([
				"Draft Page {$i}",
				"draft-{$i}-pub",
				'{"blocks":[]}',
				$userId,
				'draft',
				null,
				$now,
				$now
			]);
		}

		// Query only published pages
		$stmt = $this->pdo->prepare(
			"SELECT * FROM pages WHERE status = ? ORDER BY title"
		);
		$stmt->execute( ['published'] );
		$publishedPages = $stmt->fetchAll();

		$this->assertCount( 3, $publishedPages );
		$this->assertEquals( 'Published Page 1', $publishedPages[0]['title'] );
		$this->assertEquals( 'Published Page 2', $publishedPages[1]['title'] );
		$this->assertEquals( 'Published Page 3', $publishedPages[2]['title'] );
	}
}
