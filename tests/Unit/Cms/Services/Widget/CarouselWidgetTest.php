<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;
use Neuron\Cms\Repositories\ICarouselRepository;
use Neuron\Cms\Services\Widget\CarouselWidget;
use PHPUnit\Framework\TestCase;

class CarouselWidgetTest extends TestCase
{
	private $repository;
	private CarouselWidget $widget;

	protected function setUp(): void
	{
		$this->repository = $this->createMock( ICarouselRepository::class );
		$this->widget = new CarouselWidget( $this->repository );
	}

	public function testGetNameReturnsCarousel(): void
	{
		$this->assertSame( 'carousel', $this->widget->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$description = $this->widget->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testGetAttributesIncludesSlugDisplayTitleAndInterval(): void
	{
		$attrs = $this->widget->getAttributes();

		$this->assertArrayHasKey( 'slug', $attrs );
		$this->assertArrayHasKey( 'display', $attrs );
		$this->assertArrayHasKey( 'title', $attrs );
		$this->assertArrayHasKey( 'interval', $attrs );
	}

	public function testRenderWithoutSlugReturnsComment(): void
	{
		$this->repository->expects( $this->never() )->method( 'findBySlug' );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<!-- Carousel widget: slug is required -->', $result );
	}

	public function testRenderWithUnknownSlugReturnsComment(): void
	{
		$this->repository->method( 'findBySlug' )->with( 'missing' )->willReturn( null );

		$result = $this->widget->render( [ 'slug' => 'missing' ] );

		$this->assertStringContainsString( '<!-- Carousel widget: carousel not found -->', $result );
	}

	public function testRenderWithEmptyCarouselShowsEmptyState(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );

		$this->repository->method( 'findBySlug' )->with( 'hero' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->with( 1 )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'hero' ] );

		$this->assertStringContainsString( 'cms-carousel', $result );
		$this->assertStringContainsString( 'No slides are listed yet.', $result );
	}

	public function testRenderSliderIncludesImageCaptionAndLink(): void
	{
		$carousel = $this->carousel( 2, 'Hero', 'hero' );
		$slide = $this->slide( [
			'image_url' => 'https://cdn.example.com/hero.jpg',
			'heading' => 'Welcome',
			'caption' => 'Join us tonight',
			'link_url' => '/pages/about',
			'link_label' => 'About us'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'hero', 'title' => 'Homepage' ] );

		$this->assertStringContainsString( 'cms-carousel-title', $result );
		$this->assertStringContainsString( 'Homepage', $result );
		$this->assertStringContainsString( 'carousel slide', $result );
		$this->assertStringContainsString( 'https://cdn.example.com/hero.jpg', $result );
		$this->assertStringContainsString( 'Welcome', $result );
		$this->assertStringContainsString( 'Join us tonight', $result );
		$this->assertStringContainsString( 'href="/pages/about"', $result );
		$this->assertStringContainsString( 'About us', $result );
		$this->assertStringContainsString( 'data-carousel="hero"', $result );
		$this->assertStringContainsString( 'data-bs-interval="5000"', $result );
		$this->assertStringNotContainsString( 'carousel-indicators', $result );
	}

	public function testRenderSliderWithMultipleSlidesShowsControls(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );
		$slides = [
			$this->slide( [ 'image_url' => 'https://cdn.example.com/a.jpg', 'heading' => 'A' ] ),
			$this->slide( [ 'image_url' => 'https://cdn.example.com/b.jpg', 'heading' => 'B' ] )
		];

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( $slides );

		$result = $this->widget->render( [ 'slug' => 'hero' ] );

		$this->assertStringContainsString( 'carousel-indicators', $result );
		$this->assertStringContainsString( 'carousel-control-prev', $result );
		$this->assertStringContainsString( 'carousel-control-next', $result );
	}

	public function testRenderIntervalZeroDisablesAutoplay(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );
		$slide = $this->slide( [ 'image_url' => 'https://cdn.example.com/a.jpg' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'hero', 'interval' => 0 ] );

		$this->assertStringContainsString( 'data-bs-interval="false"', $result );
	}

	public function testRenderGalleryUsesLightbox(): void
	{
		$carousel = $this->carousel( 1, 'Camp', 'camp', 'gallery' );
		$slide = $this->slide( [ 'image_url' => 'https://cdn.example.com/camp.jpg', 'alt_text' => 'Campers' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'camp' ] );

		$this->assertStringContainsString( 'cms-carousel--gallery', $result );
		$this->assertStringContainsString( 'cms-carousel-gallery', $result );
		$this->assertStringContainsString( 'data-bs-toggle="modal"', $result );
		$this->assertStringContainsString( 'alt="Campers"', $result );
	}

	public function testRenderDisplayAttributeOverridesCarouselDefault(): void
	{
		$carousel = $this->carousel( 1, 'Partners', 'partners', 'slider' );
		$slide = $this->slide( [ 'image_url' => 'https://cdn.example.com/logo.png', 'link_url' => 'https://example.com' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'partners', 'display' => 'logos' ] );

		$this->assertStringContainsString( 'cms-carousel--logos', $result );
		$this->assertStringContainsString( 'cms-carousel-logo', $result );
		$this->assertStringContainsString( 'href="https://example.com"', $result );
		$this->assertStringNotContainsString( 'carousel slide', $result );
	}

	public function testRenderOmitsHeadingWhenTitleEmpty(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'hero' ] );

		$this->assertStringNotContainsString( 'cms-carousel-title', $result );
	}

	public function testRenderAltFallsBackToHeadingThenCarouselName(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );
		$slide = $this->slide( [ 'image_url' => 'https://cdn.example.com/a.jpg', 'heading' => 'Banner' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'hero' ] );

		$this->assertStringContainsString( 'alt="Banner"', $result );
	}

	public function testRenderEscapesHtmlInSlideData(): void
	{
		$carousel = $this->carousel( 1, 'Hero', 'hero' );
		$slide = $this->slide( [
			'image_url' => 'https://cdn.example.com/"onclick="alert(1)',
			'heading' => '<script>alert("XSS")</script>',
			'caption' => '<img src=x onerror=alert(1)>',
			'link_url' => 'https://example.com/"onclick="alert(1)',
			'alt_text' => '<b>alt</b>'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $carousel );
		$this->repository->method( 'getSlides' )->willReturn( [ $slide ] );

		$result = $this->widget->render( [ 'slug' => 'hero' ] );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '<img src=x', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	private function carousel( int $id, string $name, string $slug, string $display = 'slider' ): Carousel
	{
		$carousel = new Carousel();
		$carousel->setId( $id );
		$carousel->setName( $name );
		$carousel->setSlug( $slug );
		$carousel->setDisplay( $display );

		return $carousel;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function slide( array $data ): CarouselSlide
	{
		$slide = new CarouselSlide();
		$slide->setImageUrl( $data['image_url'] ?? 'https://cdn.example.com/slide.jpg' );
		$slide->setAltText( $data['alt_text'] ?? null );
		$slide->setHeading( $data['heading'] ?? null );
		$slide->setCaption( $data['caption'] ?? null );
		$slide->setLinkUrl( $data['link_url'] ?? null );
		$slide->setLinkLabel( $data['link_label'] ?? null );

		return $slide;
	}
}
