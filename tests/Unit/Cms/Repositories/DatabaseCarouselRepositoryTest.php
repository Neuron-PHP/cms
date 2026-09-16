<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;
use Neuron\Cms\Repositories\DatabaseCarouselRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseCarouselRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseCarouselRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE carousels (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				description TEXT,
				display VARCHAR(32) NOT NULL DEFAULT 'slider',
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$this->pdo->exec( "
			CREATE TABLE carousel_slides (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				carousel_id INTEGER NOT NULL,
				image_url VARCHAR(512) NOT NULL,
				alt_text VARCHAR(255),
				heading VARCHAR(255),
				caption TEXT,
				link_url VARCHAR(512),
				link_label VARCHAR(255),
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

		$this->repository = new DatabaseCarouselRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function makeCarousel( string $name = 'Hero', string $slug = 'hero', string $display = 'slider' ): Carousel
	{
		$carousel = new Carousel();
		$carousel->setName( $name );
		$carousel->setSlug( $slug );
		$carousel->setDescription( 'Homepage hero' );
		$carousel->setDisplay( $display );

		return $this->repository->create( $carousel );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function makeSlide( int $carouselId, array $overrides = [] ): CarouselSlide
	{
		$slide = new CarouselSlide();
		$slide->setCarouselId( $carouselId );
		$slide->setImageUrl( $overrides['image_url'] ?? 'https://cdn.example.com/slide.jpg' );
		$slide->setAltText( $overrides['alt_text'] ?? null );
		$slide->setHeading( $overrides['heading'] ?? 'Welcome' );
		$slide->setCaption( $overrides['caption'] ?? null );
		$slide->setLinkUrl( $overrides['link_url'] ?? null );
		$slide->setLinkLabel( $overrides['link_label'] ?? null );
		$slide->setSortOrder( $overrides['sort_order'] ?? 0 );

		return $this->repository->createSlide( $slide );
	}

	public function testAllReturnsEmptyArrayWhenNoCarousels(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$carousel = $this->makeCarousel();

		$found = $this->repository->findById( $carousel->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Hero', $found->getName() );
		$this->assertSame( 'hero', $found->getSlug() );
		$this->assertSame( 'Homepage hero', $found->getDescription() );
		$this->assertSame( 'slider', $found->getDisplay() );
	}

	public function testFindBySlug(): void
	{
		$this->makeCarousel( 'Partners', 'partners', 'logos' );

		$found = $this->repository->findBySlug( 'partners' );

		$this->assertNotNull( $found );
		$this->assertSame( 'logos', $found->getDisplay() );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testAllReturnsCarouselsSortedByName(): void
	{
		$this->makeCarousel( 'Partners', 'partners' );
		$this->makeCarousel( 'Hero', 'hero' );

		$carousels = $this->repository->all();

		$this->assertCount( 2, $carousels );
		$this->assertSame( 'Hero', $carousels[0]->getName() );
		$this->assertSame( 'Partners', $carousels[1]->getName() );
	}

	public function testUpdateChangesFields(): void
	{
		$carousel = $this->makeCarousel();
		$carousel->setName( 'Banner' );
		$carousel->setDisplay( 'gallery' );
		$carousel->setDescription( null );

		$this->repository->update( $carousel );

		$found = $this->repository->findById( $carousel->getId() );
		$this->assertSame( 'Banner', $found->getName() );
		$this->assertSame( 'gallery', $found->getDisplay() );
		$this->assertNull( $found->getDescription() );
	}

	public function testSlugExists(): void
	{
		$carousel = $this->makeCarousel();

		$this->assertTrue( $this->repository->slugExists( 'hero' ) );
		$this->assertFalse( $this->repository->slugExists( 'hero', $carousel->getId() ) );
		$this->assertFalse( $this->repository->slugExists( 'missing' ) );
	}

	public function testDeleteRemovesCarouselAndSlides(): void
	{
		$carousel = $this->makeCarousel();
		$this->makeSlide( $carousel->getId() );

		$this->assertTrue( $this->repository->delete( $carousel ) );
		$this->assertNull( $this->repository->findById( $carousel->getId() ) );
		$this->assertSame( 0, $this->repository->countSlides( $carousel->getId() ) );
	}

	public function testCreateAndFindSlide(): void
	{
		$carousel = $this->makeCarousel();
		$slide = $this->makeSlide( $carousel->getId(), [
			'image_url' => 'https://cdn.example.com/camp.jpg',
			'alt_text' => 'Campers',
			'heading' => 'Summer Camp',
			'caption' => 'Join us this July',
			'link_url' => '/pages/camp',
			'link_label' => 'Learn more',
			'sort_order' => 3
		] );

		$found = $this->repository->findSlideById( $slide->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'https://cdn.example.com/camp.jpg', $found->getImageUrl() );
		$this->assertSame( 'Campers', $found->getAltText() );
		$this->assertSame( 'Summer Camp', $found->getHeading() );
		$this->assertSame( 'Join us this July', $found->getCaption() );
		$this->assertSame( '/pages/camp', $found->getLinkUrl() );
		$this->assertSame( 'Learn more', $found->getLinkLabel() );
		$this->assertSame( 3, $found->getSortOrder() );
	}

	public function testGetSlidesOrdersBySortThenId(): void
	{
		$carousel = $this->makeCarousel();
		$this->makeSlide( $carousel->getId(), [ 'heading' => 'Third', 'sort_order' => 2 ] );
		$first = $this->makeSlide( $carousel->getId(), [ 'heading' => 'First', 'sort_order' => 1 ] );
		$second = $this->makeSlide( $carousel->getId(), [ 'heading' => 'Second', 'sort_order' => 1 ] );

		$slides = $this->repository->getSlides( $carousel->getId() );

		$this->assertCount( 3, $slides );
		$this->assertSame( 'First', $slides[0]->getHeading() );
		$this->assertSame( 'Second', $slides[1]->getHeading() );
		$this->assertSame( 'Third', $slides[2]->getHeading() );
		$this->assertSame( $first->getId(), $slides[0]->getId() );
		$this->assertSame( $second->getId(), $slides[1]->getId() );
	}

	public function testUpdateSlideChangesFields(): void
	{
		$carousel = $this->makeCarousel();
		$slide = $this->makeSlide( $carousel->getId() );
		$slide->setHeading( 'Updated heading' );
		$slide->setLinkUrl( null );

		$this->repository->updateSlide( $slide );

		$found = $this->repository->findSlideById( $slide->getId() );
		$this->assertSame( 'Updated heading', $found->getHeading() );
		$this->assertNull( $found->getLinkUrl() );
	}

	public function testDeleteSlideRemovesRow(): void
	{
		$carousel = $this->makeCarousel();
		$slide = $this->makeSlide( $carousel->getId() );

		$this->assertTrue( $this->repository->deleteSlide( $slide ) );
		$this->assertNull( $this->repository->findSlideById( $slide->getId() ) );
	}

	public function testCountSlidesAndNextSortOrder(): void
	{
		$carousel = $this->makeCarousel();

		$this->assertSame( 0, $this->repository->countSlides( $carousel->getId() ) );
		$this->assertSame( 0, $this->repository->nextSlideSortOrder( $carousel->getId() ) );

		$this->makeSlide( $carousel->getId(), [ 'sort_order' => 0 ] );
		$this->makeSlide( $carousel->getId(), [ 'sort_order' => 4 ] );

		$this->assertSame( 2, $this->repository->countSlides( $carousel->getId() ) );
		$this->assertSame( 5, $this->repository->nextSlideSortOrder( $carousel->getId() ) );
	}

	public function testFindSlideByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findSlideById( 999 ) );
	}

	public function testInvalidDisplayFallsBackToSlider(): void
	{
		$carousel = $this->makeCarousel( 'Odd', 'odd', 'not-a-mode' );

		$this->assertSame( 'slider', $carousel->getDisplay() );
	}
}
