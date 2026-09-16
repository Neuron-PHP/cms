<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Enums\TestimonialDisplay;
use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;
use Neuron\Cms\Repositories\ITestimonialRepository;
use Neuron\Cms\Services\Widget\TestimonialWidget;
use PHPUnit\Framework\TestCase;

class TestimonialWidgetTest extends TestCase
{
	private $repository;
	private TestimonialWidget $widget;

	protected function setUp(): void
	{
		$this->repository = $this->createMock( ITestimonialRepository::class );
		$this->widget = new TestimonialWidget( $this->repository );
	}

	public function testGetNameReturnsTestimonial(): void
	{
		$this->assertSame( 'testimonial', $this->widget->getName() );
	}

	public function testGetAttributesIncludesSlugDisplayAndTitle(): void
	{
		$attrs = $this->widget->getAttributes();

		$this->assertArrayHasKey( 'slug', $attrs );
		$this->assertArrayHasKey( 'display', $attrs );
		$this->assertArrayHasKey( 'title', $attrs );
	}

	public function testRenderWithoutSlugReturnsComment(): void
	{
		$this->repository->expects( $this->never() )->method( 'findBySlug' );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<!-- Testimonial widget: slug is required -->', $result );
	}

	public function testRenderWithUnknownSlugReturnsComment(): void
	{
		$this->repository->method( 'findBySlug' )->with( 'missing' )->willReturn( null );

		$result = $this->widget->render( [ 'slug' => 'missing' ] );

		$this->assertStringContainsString( '<!-- Testimonial widget: collection not found -->', $result );
	}

	public function testRenderWithEmptyCollectionShowsEmptyState(): void
	{
		$collection = $this->collection( 1, 'Success Stories', 'success-stories' );

		$this->repository->method( 'findBySlug' )->with( 'success-stories' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->with( 1 )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'success-stories' ] );

		$this->assertStringContainsString( 'cms-testimonial', $result );
		$this->assertStringContainsString( 'No testimonials are listed yet.', $result );
	}

	public function testRenderCardsIncludesQuoteAndCitation(): void
	{
		$collection = $this->collection( 2, 'Stories', 'stories' );
		$quote = $this->quote( [
			'quote' => 'A second chance that worked.',
			'attribution' => 'Alex',
			'role' => 'Parent',
			'organization' => 'Sarasota',
			'image_url' => 'https://cdn.example.com/alex.jpg'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->willReturn( [ $quote ] );

		$result = $this->widget->render( [ 'slug' => 'stories', 'title' => 'What families say' ] );

		$this->assertStringContainsString( 'cms-testimonial--cards', $result );
		$this->assertStringContainsString( 'What families say', $result );
		$this->assertStringContainsString( 'A second chance that worked.', $result );
		$this->assertStringContainsString( 'Alex, Parent, Sarasota', $result );
		$this->assertStringContainsString( 'https://cdn.example.com/alex.jpg', $result );
	}

	public function testRenderListUsesBlockquotes(): void
	{
		$collection = $this->collection( 3, 'List', 'list', TestimonialDisplay::LIST->value );
		$quote = $this->quote( [ 'quote' => 'Listed quote' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->willReturn( [ $quote ] );

		$result = $this->widget->render( [ 'slug' => 'list' ] );

		$this->assertStringContainsString( 'cms-testimonial--list', $result );
		$this->assertStringContainsString( '<blockquote', $result );
		$this->assertStringContainsString( 'Listed quote', $result );
	}

	public function testRenderFeaturedWithOneQuoteSkipsCarousel(): void
	{
		$collection = $this->collection( 4, 'Home', 'home', TestimonialDisplay::FEATURED->value );
		$quote = $this->quote( [ 'quote' => 'Single featured quote' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->willReturn( [ $quote ] );

		$result = $this->widget->render( [ 'slug' => 'home' ] );

		$this->assertStringContainsString( 'cms-testimonial-card--featured', $result );
		$this->assertStringNotContainsString( 'carousel slide', $result );
	}

	public function testRenderFeaturedWithMultipleQuotesUsesCarousel(): void
	{
		$collection = $this->collection( 5, 'Home', 'home' );
		$first = $this->quote( [ 'quote' => 'First quote' ] );
		$second = $this->quote( [ 'quote' => 'Second quote' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->willReturn( [ $first, $second ] );

		$result = $this->widget->render( [ 'slug' => 'home', 'display' => 'featured' ] );

		$this->assertStringContainsString( 'carousel slide', $result );
		$this->assertStringContainsString( 'First quote', $result );
		$this->assertStringContainsString( 'Second quote', $result );
	}

	public function testDisplayAttributeOverridesCollectionDefault(): void
	{
		$collection = $this->collection( 6, 'Stories', 'stories', TestimonialDisplay::CARDS->value );
		$quote = $this->quote( [ 'quote' => 'Override me' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $collection );
		$this->repository->method( 'getQuotes' )->willReturn( [ $quote ] );

		$result = $this->widget->render( [ 'slug' => 'stories', 'display' => 'list' ] );

		$this->assertStringContainsString( 'cms-testimonial--list', $result );
	}

	private function collection( int $id, string $name, string $slug, string $display = TestimonialDisplay::CARDS->value ): Testimonial
	{
		$collection = new Testimonial();
		$collection->setId( $id );
		$collection->setName( $name );
		$collection->setSlug( $slug );
		$collection->setDisplay( $display );

		return $collection;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function quote( array $overrides = [] ): TestimonialQuote
	{
		$quote = new TestimonialQuote();
		$quote->setQuote( $overrides['quote'] ?? 'Quote' );
		$quote->setAttribution( $overrides['attribution'] ?? null );
		$quote->setRole( $overrides['role'] ?? null );
		$quote->setOrganization( $overrides['organization'] ?? null );
		$quote->setImageUrl( $overrides['image_url'] ?? null );

		return $quote;
	}
}
