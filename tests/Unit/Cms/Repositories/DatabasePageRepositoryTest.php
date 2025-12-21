<?php

namespace Tests\Unit\Cms\Repositories;

use DateTimeImmutable;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Orm\Model;
use PHPUnit\Framework\TestCase;
use PDO;

class DatabasePageRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabasePageRepository $repository;

	protected function setUp(): void
	{
		// Configure database settings
		$config = [
			'adapter' => 'sqlite',
			'name' => ':memory:'
		];

		// Mock SettingManager with database configuration
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		// Create repository
		$this->repository = new DatabasePageRepository( $settings );

		// Get PDO connection via reflection
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setAccessible( true );
		$this->pdo = $property->getValue( $this->repository );

		// Initialize ORM with the PDO connection
		Model::setPdo( $this->pdo );

		// Create tables
		$this->createTables();
	}

	private function createTables(): void
	{
		// Create users table
		$this->pdo->exec( "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				role VARCHAR(50) DEFAULT 'subscriber',
				status VARCHAR(50) DEFAULT 'active',
				email_verified BOOLEAN DEFAULT 0,
				two_factor_secret VARCHAR(255) NULL,
				two_factor_recovery_codes TEXT NULL,
				remember_token VARCHAR(255) NULL,
				failed_login_attempts INTEGER DEFAULT 0,
				locked_until TIMESTAMP NULL,
				last_login_at TIMESTAMP NULL,
				timezone VARCHAR(50) DEFAULT 'UTC',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		" );

		// Create pages table
		$this->pdo->exec( "
			CREATE TABLE pages (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				title VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				content TEXT NOT NULL,
				template VARCHAR(100) DEFAULT 'default',
				meta_title VARCHAR(255),
				meta_description TEXT,
				meta_keywords VARCHAR(255),
				author_id INTEGER NOT NULL,
				status VARCHAR(50) DEFAULT 'draft',
				view_count INTEGER DEFAULT 0,
				published_at TIMESTAMP NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				FOREIGN KEY (author_id) REFERENCES users(id)
			)
		" );
	}

	private function createTestUser(): User
	{
		$unique = uniqid();
		return User::create([
			'username' => 'testuser_' . $unique,
			'email' => "test_{$unique}@example.com",
			'password_hash' => 'hash123'
		]);
	}

	private function createTestPage( array $overrides = [] ): Page
	{
		$user = $this->createTestUser();

		$data = array_merge([
			'title' => 'Test Page',
			'slug' => 'test-page-' . uniqid(),
			'content' => '{"blocks":[]}',
			'author_id' => $user->getId(),
			'status' => 'draft'
		], $overrides);

		return Page::create( $data );
	}

	public function testFindByIdReturnsPage(): void
	{
		$page = $this->createTestPage();

		$found = $this->repository->findById( $page->getId() );

		$this->assertNotNull( $found );
		$this->assertEquals( $page->getId(), $found->getId() );
		$this->assertEquals( $page->getTitle(), $found->getTitle() );
	}

	public function testFindByIdReturnsNullForNonexistent(): void
	{
		$found = $this->repository->findById( 9999 );

		$this->assertNull( $found );
	}

	public function testFindBySlugReturnsPage(): void
	{
		$page = $this->createTestPage([ 'slug' => 'about-us' ]);

		$found = $this->repository->findBySlug( 'about-us' );

		$this->assertNotNull( $found );
		$this->assertEquals( $page->getId(), $found->getId() );
		$this->assertEquals( 'about-us', $found->getSlug() );
	}

	public function testFindBySlugReturnsNullForNonexistent(): void
	{
		$found = $this->repository->findBySlug( 'nonexistent-slug' );

		$this->assertNull( $found );
	}

	public function testCreateSavesPage(): void
	{
		$user = $this->createTestUser();

		$page = new Page();
		$page->setTitle( 'New Page' );
		$page->setSlug( 'new-page' );
		$page->setContent( '{"blocks":[]}' );
		$page->setAuthorId( $user->getId() );
		$page->setStatus( Page::STATUS_DRAFT );

		$result = $this->repository->create( $page );

		$this->assertGreaterThan( 0, $result->getId() );
		$this->assertEquals( 'New Page', $result->getTitle() );

		// Verify it was saved to database
		$found = $this->repository->findById( $result->getId() );
		$this->assertNotNull( $found );
	}

	public function testCreateThrowsExceptionForDuplicateSlug(): void
	{
		$user = $this->createTestUser();

		// Create first page
		$this->createTestPage([ 'slug' => 'duplicate-slug' ]);

		// Try to create second page with same slug
		$page = new Page();
		$page->setTitle( 'Duplicate' );
		$page->setSlug( 'duplicate-slug' );
		$page->setContent( '{"blocks":[]}' );
		$page->setAuthorId( $user->getId() );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );

		$this->repository->create( $page );
	}

	public function testUpdateModifiesPage(): void
	{
		$page = $this->createTestPage();
		$originalId = $page->getId();

		$page->setTitle( 'Updated Title' );
		$result = $this->repository->update( $page );

		$this->assertTrue( $result );

		$found = $this->repository->findById( $originalId );
		$this->assertEquals( 'Updated Title', $found->getTitle() );
	}

	public function testUpdateReturnsFalseForPageWithoutId(): void
	{
		$page = new Page();
		$page->setTitle( 'No ID' );

		$result = $this->repository->update( $page );

		$this->assertFalse( $result );
	}

	public function testUpdateThrowsExceptionForDuplicateSlug(): void
	{
		// Create two pages
		$page1 = $this->createTestPage([ 'slug' => 'page-one' ]);
		$page2 = $this->createTestPage([ 'slug' => 'page-two' ]);

		// Try to update page2 with page1's slug
		$page2->setSlug( 'page-one' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Slug already exists' );

		$this->repository->update( $page2 );
	}

	public function testDeleteRemovesPage(): void
	{
		$page = $this->createTestPage();
		$pageId = $page->getId();

		$result = $this->repository->delete( $pageId );

		$this->assertTrue( $result );

		$found = $this->repository->findById( $pageId );
		$this->assertNull( $found );
	}

	public function testDeleteReturnsFalseForNonexistent(): void
	{
		$result = $this->repository->delete( 9999 );

		$this->assertFalse( $result );
	}

	public function testAllReturnsAllPages(): void
	{
		$this->createTestPage([ 'title' => 'Page 1' ]);
		$this->createTestPage([ 'title' => 'Page 2' ]);
		$this->createTestPage([ 'title' => 'Page 3' ]);

		$pages = $this->repository->all();

		$this->assertCount( 3, $pages );
	}

	public function testAllFiltersByStatus(): void
	{
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);
		$this->createTestPage([ 'status' => Page::STATUS_PUBLISHED ]);
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);

		$drafts = $this->repository->all( Page::STATUS_DRAFT );
		$published = $this->repository->all( Page::STATUS_PUBLISHED );

		$this->assertCount( 2, $drafts );
		$this->assertCount( 1, $published );
	}

	public function testAllRespectsLimitAndOffset(): void
	{
		for( $i = 1; $i <= 5; $i++ )
		{
			$this->createTestPage([ 'title' => "Page {$i}" ]);
		}

		$pages = $this->repository->all( null, 2, 1 );

		$this->assertCount( 2, $pages );
	}

	public function testGetPublishedReturnsOnlyPublished(): void
	{
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);
		$this->createTestPage([ 'status' => Page::STATUS_PUBLISHED ]);
		$this->createTestPage([ 'status' => Page::STATUS_PUBLISHED ]);

		$published = $this->repository->getPublished();

		$this->assertCount( 2, $published );
		foreach( $published as $page )
		{
			$this->assertEquals( Page::STATUS_PUBLISHED, $page->getStatus() );
		}
	}

	public function testGetDraftsReturnsOnlyDrafts(): void
	{
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);
		$this->createTestPage([ 'status' => Page::STATUS_PUBLISHED ]);
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);

		$drafts = $this->repository->getDrafts();

		$this->assertCount( 2, $drafts );
		foreach( $drafts as $page )
		{
			$this->assertEquals( Page::STATUS_DRAFT, $page->getStatus() );
		}
	}

	public function testGetByAuthorReturnsAuthorPages(): void
	{
		$user1 = $this->createTestUser();
		$user2 = User::create([
			'username' => 'user2',
			'email' => 'user2@example.com',
			'password_hash' => 'hash'
		]);

		$this->createTestPage([ 'author_id' => $user1->getId() ]);
		$this->createTestPage([ 'author_id' => $user1->getId() ]);
		$this->createTestPage([ 'author_id' => $user2->getId() ]);

		$user1Pages = $this->repository->getByAuthor( $user1->getId() );

		$this->assertCount( 2, $user1Pages );
	}

	public function testGetByAuthorFiltersByStatus(): void
	{
		$user = $this->createTestUser();

		$this->createTestPage([ 'author_id' => $user->getId(), 'status' => Page::STATUS_DRAFT ]);
		$this->createTestPage([ 'author_id' => $user->getId(), 'status' => Page::STATUS_PUBLISHED ]);

		$drafts = $this->repository->getByAuthor( $user->getId(), Page::STATUS_DRAFT );

		$this->assertCount( 1, $drafts );
		$this->assertEquals( Page::STATUS_DRAFT, $drafts[0]->getStatus() );
	}

	public function testCountReturnsTotal(): void
	{
		$this->createTestPage();
		$this->createTestPage();
		$this->createTestPage();

		$count = $this->repository->count();

		$this->assertEquals( 3, $count );
	}

	public function testCountFiltersByStatus(): void
	{
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);
		$this->createTestPage([ 'status' => Page::STATUS_PUBLISHED ]);
		$this->createTestPage([ 'status' => Page::STATUS_DRAFT ]);

		$draftCount = $this->repository->count( Page::STATUS_DRAFT );
		$publishedCount = $this->repository->count( Page::STATUS_PUBLISHED );

		$this->assertEquals( 2, $draftCount );
		$this->assertEquals( 1, $publishedCount );
	}

	public function testIncrementViewCountIncreasesCount(): void
	{
		$page = $this->createTestPage();
		$pageId = $page->getId();

		$this->repository->incrementViewCount( $pageId );
		$this->repository->incrementViewCount( $pageId );

		$found = $this->repository->findById( $pageId );
		$this->assertEquals( 2, $found->getViewCount() );
	}

	public function testIncrementViewCountReturnsFalseForNonexistent(): void
	{
		$result = $this->repository->incrementViewCount( 9999 );

		$this->assertFalse( $result );
	}
}
