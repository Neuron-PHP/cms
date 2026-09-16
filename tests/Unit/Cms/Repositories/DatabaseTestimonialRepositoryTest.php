<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Enums\TestimonialDisplay;
use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;
use Neuron\Cms\Repositories\DatabaseTestimonialRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseTestimonialRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseTestimonialRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE testimonials (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				description TEXT,
				display VARCHAR(32) NOT NULL DEFAULT 'cards',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$this->pdo->exec( "
			CREATE TABLE testimonial_quotes (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				testimonial_id INTEGER NOT NULL,
				quote TEXT NOT NULL,
				attribution VARCHAR(255),
				role VARCHAR(255),
				organization VARCHAR(255),
				image_url VARCHAR(512),
				sort_order INTEGER NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name' => ':memory:'
			] );

		$this->repository = new DatabaseTestimonialRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function makeCollection( string $name = 'Success Stories', string $slug = 'success-stories', string $display = TestimonialDisplay::CARDS->value ): Testimonial
	{
		$testimonial = new Testimonial();
		$testimonial->setName( $name );
		$testimonial->setSlug( $slug );
		$testimonial->setDescription( 'Family quotes' );
		$testimonial->setDisplay( $display );

		return $this->repository->create( $testimonial );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function makeQuote( int $testimonialId, array $overrides = [] ): TestimonialQuote
	{
		$quote = new TestimonialQuote();
		$quote->setTestimonialId( $testimonialId );
		$quote->setQuote( $overrides['quote'] ?? 'Teen Court gave our family a second chance.' );
		$quote->setAttribution( $overrides['attribution'] ?? 'Jordan' );
		$quote->setRole( $overrides['role'] ?? null );
		$quote->setOrganization( $overrides['organization'] ?? null );
		$quote->setImageUrl( $overrides['image_url'] ?? null );
		$quote->setSortOrder( $overrides['sort_order'] ?? 0 );

		return $this->repository->createQuote( $quote );
	}

	public function testAllReturnsEmptyArrayWhenNoneExist(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$collection = $this->makeCollection();

		$found = $this->repository->findById( $collection->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Success Stories', $found->getName() );
		$this->assertSame( 'success-stories', $found->getSlug() );
		$this->assertSame( 'Family quotes', $found->getDescription() );
		$this->assertSame( TestimonialDisplay::CARDS->value, $found->getDisplay() );
	}

	public function testFindBySlug(): void
	{
		$this->makeCollection( 'Homepage', 'homepage', TestimonialDisplay::FEATURED->value );

		$found = $this->repository->findBySlug( 'homepage' );

		$this->assertNotNull( $found );
		$this->assertSame( TestimonialDisplay::FEATURED->value, $found->getDisplay() );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testAllReturnsCollectionsSortedByName(): void
	{
		$this->makeCollection( 'Homepage', 'homepage' );
		$this->makeCollection( 'Alumni', 'alumni' );

		$collections = $this->repository->all();

		$this->assertCount( 2, $collections );
		$this->assertSame( 'Alumni', $collections[0]->getName() );
		$this->assertSame( 'Homepage', $collections[1]->getName() );
	}

	public function testUpdateChangesFields(): void
	{
		$collection = $this->makeCollection();
		$collection->setName( 'Stories' );
		$collection->setDisplay( TestimonialDisplay::LIST->value );
		$collection->setDescription( null );

		$this->repository->update( $collection );

		$found = $this->repository->findById( $collection->getId() );
		$this->assertSame( 'Stories', $found->getName() );
		$this->assertSame( TestimonialDisplay::LIST->value, $found->getDisplay() );
		$this->assertNull( $found->getDescription() );
	}

	public function testSlugExists(): void
	{
		$collection = $this->makeCollection();

		$this->assertTrue( $this->repository->slugExists( 'success-stories' ) );
		$this->assertFalse( $this->repository->slugExists( 'success-stories', $collection->getId() ) );
		$this->assertFalse( $this->repository->slugExists( 'missing' ) );
	}

	public function testDeleteRemovesCollectionAndQuotes(): void
	{
		$collection = $this->makeCollection();
		$this->makeQuote( $collection->getId() );

		$this->assertTrue( $this->repository->delete( $collection ) );
		$this->assertNull( $this->repository->findById( $collection->getId() ) );
		$this->assertSame( 0, $this->repository->countQuotes( $collection->getId() ) );
	}

	public function testCreateAndFindQuote(): void
	{
		$collection = $this->makeCollection();
		$quote = $this->makeQuote( $collection->getId(), [
			'quote' => 'A second chance that worked.',
			'attribution' => 'Alex',
			'role' => 'Parent',
			'organization' => 'Sarasota',
			'image_url' => 'https://cdn.example.com/alex.jpg',
			'sort_order' => 3
		] );

		$found = $this->repository->findQuoteById( $quote->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'A second chance that worked.', $found->getQuote() );
		$this->assertSame( 'Alex', $found->getAttribution() );
		$this->assertSame( 'Parent', $found->getRole() );
		$this->assertSame( 'Sarasota', $found->getOrganization() );
		$this->assertSame( 'https://cdn.example.com/alex.jpg', $found->getImageUrl() );
		$this->assertSame( 3, $found->getSortOrder() );
		$this->assertSame( 'Alex, Parent, Sarasota', $found->citation() );
	}

	public function testGetQuotesOrdersBySortThenId(): void
	{
		$collection = $this->makeCollection();
		$this->makeQuote( $collection->getId(), [ 'quote' => 'Third', 'sort_order' => 2 ] );
		$first = $this->makeQuote( $collection->getId(), [ 'quote' => 'First', 'sort_order' => 1 ] );
		$second = $this->makeQuote( $collection->getId(), [ 'quote' => 'Second', 'sort_order' => 1 ] );

		$quotes = $this->repository->getQuotes( $collection->getId() );

		$this->assertCount( 3, $quotes );
		$this->assertSame( 'First', $quotes[0]->getQuote() );
		$this->assertSame( 'Second', $quotes[1]->getQuote() );
		$this->assertSame( 'Third', $quotes[2]->getQuote() );
		$this->assertSame( $first->getId(), $quotes[0]->getId() );
		$this->assertSame( $second->getId(), $quotes[1]->getId() );
	}

	public function testUpdateQuoteChangesFields(): void
	{
		$collection = $this->makeCollection();
		$quote = $this->makeQuote( $collection->getId() );
		$quote->setQuote( 'Updated quote' );
		$quote->setImageUrl( null );

		$this->repository->updateQuote( $quote );

		$found = $this->repository->findQuoteById( $quote->getId() );
		$this->assertSame( 'Updated quote', $found->getQuote() );
		$this->assertNull( $found->getImageUrl() );
	}

	public function testDeleteQuoteRemovesRow(): void
	{
		$collection = $this->makeCollection();
		$quote = $this->makeQuote( $collection->getId() );

		$this->assertTrue( $this->repository->deleteQuote( $quote ) );
		$this->assertNull( $this->repository->findQuoteById( $quote->getId() ) );
	}

	public function testCountQuotesAndNextSortOrder(): void
	{
		$collection = $this->makeCollection();

		$this->assertSame( 0, $this->repository->countQuotes( $collection->getId() ) );
		$this->assertSame( 0, $this->repository->nextQuoteSortOrder( $collection->getId() ) );

		$this->makeQuote( $collection->getId(), [ 'sort_order' => 0 ] );
		$this->makeQuote( $collection->getId(), [ 'sort_order' => 4 ] );

		$this->assertSame( 2, $this->repository->countQuotes( $collection->getId() ) );
		$this->assertSame( 5, $this->repository->nextQuoteSortOrder( $collection->getId() ) );
	}

	public function testFindQuoteByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findQuoteById( 999 ) );
	}

	public function testInvalidDisplayFallsBackToCards(): void
	{
		$collection = $this->makeCollection( 'Odd', 'odd', 'not-a-mode' );

		$this->assertSame( TestimonialDisplay::CARDS->value, $collection->getDisplay() );
	}
}
