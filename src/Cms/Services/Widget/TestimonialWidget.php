<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Enums\TestimonialDisplay;
use Neuron\Cms\Models\Testimonial;
use Neuron\Cms\Models\TestimonialQuote;
use Neuron\Cms\Repositories\ITestimonialRepository;

/**
 * Named testimonial widget / shortcode.
 *
 *   [testimonial slug="success-stories"]
 *   [testimonial slug="homepage" display="featured" title="What families say"]
 *
 * @package Neuron\Cms\Services\Widget
 */
class TestimonialWidget implements IWidget
{
	private ITestimonialRepository $_testimonials;

	public function __construct( ITestimonialRepository $testimonials )
	{
		$this->_testimonials = $testimonials;
	}

	public function getName(): string
	{
		return 'testimonial';
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	public function render( array $attrs ): string
	{
		$slug = trim( (string) ( $attrs['slug'] ?? '' ) );

		if( $slug === '' )
		{
			return '<!-- Testimonial widget: slug is required -->';
		}

		$collection = $this->_testimonials->findBySlug( $slug );

		if( !$collection )
		{
			return '<!-- Testimonial widget: collection not found -->';
		}

		$quotes = $this->_testimonials->getQuotes( $collection->getId() );
		$title  = isset( $attrs['title'] ) ? trim( (string) $attrs['title'] ) : '';
		$display = TestimonialDisplay::fromValue(
			isset( $attrs['display'] ) ? (string) $attrs['display'] : $collection->getDisplay(),
			$collection->getDisplayMode()
		);

		return $this->renderCollection( $collection, $quotes, $display, $title );
	}

	public function getDescription(): string
	{
		return 'Display a named testimonial collection';
	}

	/**
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'slug' => 'Collection slug (required), e.g. "success-stories"',
			'display' => 'cards, list, or featured. Defaults to the collection setting.',
			'title' => 'Optional heading. Omit to render quotes without a heading.',
		];
	}

	/**
	 * @param TestimonialQuote[] $quotes
	 */
	private function renderCollection( Testimonial $collection, array $quotes, TestimonialDisplay $display, string $title ): string
	{
		$html = '<div class="cms-testimonial cms-testimonial--' . $this->esc( $display->value ) . '" data-testimonial="' . $this->esc( $collection->getSlug() ) . '">';

		if( $title !== '' )
		{
			$html .= '<h3 class="cms-testimonial-title mb-4">' . $this->esc( $title ) . '</h3>';
		}

		if( $quotes === [] )
		{
			$html .= '<p class="text-muted mb-0">No testimonials are listed yet.</p></div>';

			return $html;
		}

		$html .= match( $display )
		{
			TestimonialDisplay::LIST => $this->renderList( $quotes ),
			TestimonialDisplay::FEATURED => $this->renderFeatured( $collection, $quotes ),
			default => $this->renderCards( $quotes ),
		};

		$html .= '</div>';
		$html .= $this->styles();

		return $html;
	}

	/**
	 * @param TestimonialQuote[] $quotes
	 */
	private function renderCards( array $quotes ): string
	{
		$html = '<div class="row g-4 cms-testimonial-grid">';

		foreach( $quotes as $quote )
		{
			$html .= '<div class="col-md-6 col-lg-4">';
			$html .= $this->card( $quote );
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * @param TestimonialQuote[] $quotes
	 */
	private function renderList( array $quotes ): string
	{
		$html = '<div class="cms-testimonial-list">';

		foreach( $quotes as $quote )
		{
			$html .= $this->blockquote( $quote, 'mb-4' );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * @param TestimonialQuote[] $quotes
	 */
	private function renderFeatured( Testimonial $collection, array $quotes ): string
	{
		if( count( $quotes ) === 1 )
		{
			return $this->card( $quotes[0], true );
		}

		$id = 'cms-testimonial-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $collection->getSlug() ) );
		$html = '<div id="' . $this->esc( $id ) . '" class="carousel slide cms-testimonial-featured" data-bs-ride="carousel" data-bs-pause="hover">';
		$html .= '<div class="carousel-inner">';

		foreach( $quotes as $index => $quote )
		{
			$active = $index === 0 ? ' active' : '';
			$html .= '<div class="carousel-item' . $active . '">';
			$html .= $this->card( $quote, true );
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '<button class="carousel-control-prev" type="button" data-bs-target="#' . $this->esc( $id ) . '" data-bs-slide="prev">';
		$html .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
		$html .= '<span class="visually-hidden">Previous</span>';
		$html .= '</button>';
		$html .= '<button class="carousel-control-next" type="button" data-bs-target="#' . $this->esc( $id ) . '" data-bs-slide="next">';
		$html .= '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
		$html .= '<span class="visually-hidden">Next</span>';
		$html .= '</button>';
		$html .= '</div>';

		return $html;
	}

	private function card( TestimonialQuote $quote, bool $featured = false ): string
	{
		$classes = 'card cms-testimonial-card border-0 shadow-sm h-100';

		if( $featured )
		{
			$classes .= ' cms-testimonial-card--featured';
		}

		$html = '<div class="' . $classes . '"><div class="card-body p-4">';
		$html .= $this->quoteBody( $quote );
		$html .= '</div></div>';

		return $html;
	}

	private function blockquote( TestimonialQuote $quote, string $class = '' ): string
	{
		$html = '<blockquote class="cms-testimonial-quote ' . $this->esc( $class ) . '">';
		$html .= $this->quoteBody( $quote );
		$html .= '</blockquote>';

		return $html;
	}

	private function quoteBody( TestimonialQuote $quote ): string
	{
		$html = '<p class="cms-testimonial-text mb-3">' . nl2br( $this->esc( $quote->getQuote() ) ) . '</p>';

		$citation = $quote->citation();
		$image = $quote->getImageUrl();

		if( $citation === '' && !$image )
		{
			return $html;
		}

		$html .= '<footer class="cms-testimonial-cite d-flex align-items-center gap-3">';

		if( $image )
		{
			$alt = $this->esc( $quote->getAttribution() ?: 'Testimonial photo' );
			$html .= '<img class="cms-testimonial-photo" src="' . $this->esc( $image ) . '" alt="' . $alt . '" loading="lazy">';
		}

		if( $citation !== '' )
		{
			$html .= '<cite class="small text-muted">' . $this->esc( $citation ) . '</cite>';
		}

		$html .= '</footer>';

		return $html;
	}

	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private function styles(): string
	{
		return '<style>'
			. '.cms-testimonial-text{font-size:1.05rem;}'
			. '.cms-testimonial-card--featured .cms-testimonial-text{font-size:1.35rem;}'
			. '.cms-testimonial-photo{width:48px;height:48px;object-fit:cover;border-radius:50%;}'
			. '.cms-testimonial-featured .carousel-control-prev,.cms-testimonial-featured .carousel-control-next{width:3rem;}'
			. '</style>';
	}
}
