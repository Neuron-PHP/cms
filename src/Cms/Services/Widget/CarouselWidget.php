<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Enums\CarouselDisplay;
use Neuron\Cms\Models\Carousel;
use Neuron\Cms\Models\CarouselSlide;
use Neuron\Cms\Repositories\ICarouselRepository;

/**
 * Named image carousel widget / shortcode.
 *
 * Renders a named carousel as a slider, gallery, or logo strip.
 *
 *   [carousel slug="hero"]
 *   [carousel slug="partners" display="logos"]
 *   [carousel slug="camp" display="gallery" title="Camp photos"]
 *   [carousel slug="hero" interval="0"]
 *
 * Missing or unknown slug renders nothing visible (an HTML comment).
 *
 * @package Neuron\Cms\Services\Widget
 */
class CarouselWidget implements IWidget
{
	private ICarouselRepository $_carousels;

	public function __construct( ICarouselRepository $carousels )
	{
		$this->_carousels = $carousels;
	}

	public function getName(): string
	{
		return 'carousel';
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	public function render( array $attrs ): string
	{
		$slug = trim( (string) ( $attrs['slug'] ?? '' ) );

		if( $slug === '' )
		{
			return '<!-- Carousel widget: slug is required -->';
		}

		$carousel = $this->_carousels->findBySlug( $slug );

		if( !$carousel )
		{
			return '<!-- Carousel widget: carousel not found -->';
		}

		$slides  = $this->_carousels->getSlides( $carousel->getId() );
		$title   = isset( $attrs['title'] ) ? trim( (string) $attrs['title'] ) : '';
		$display = CarouselDisplay::fromValue(
			isset( $attrs['display'] ) ? (string) $attrs['display'] : $carousel->getDisplay(),
			$carousel->getDisplayMode()
		);
		$interval = $this->interval( $attrs );

		return $this->renderCollection( $carousel, $slides, $display, $title, $interval );
	}

	public function getDescription(): string
	{
		return 'Display a named image carousel, gallery, or logo strip';
	}

	/**
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'slug'     => 'Carousel slug (required), e.g. "hero" or "partners"',
			'display'  => 'Optional override: slider, gallery, or logos',
			'title'    => 'Optional heading. Omit to render without a heading.',
			'interval' => 'Slider autoplay interval in milliseconds. 0 disables. Default 5000.',
		];
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	private function interval( array $attrs ): int
	{
		if( !isset( $attrs['interval'] ) )
		{
			return 5000;
		}

		$interval = (int) $attrs['interval'];

		return max( 0, $interval );
	}

	/**
	 * @param CarouselSlide[] $slides
	 */
	private function renderCollection(
		Carousel $carousel,
		array $slides,
		CarouselDisplay $display,
		string $title,
		int $interval
	): string
	{
		$id = 'cms-carousel-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $carousel->getSlug() ) );

		$html = '<div class="cms-carousel cms-carousel--' . $this->esc( $display->value ) . '" data-carousel="' . $this->esc( $carousel->getSlug() ) . '">';

		if( $title !== '' )
		{
			$html .= '<h3 class="cms-carousel-title mb-4">' . $this->esc( $title ) . '</h3>';
		}

		if( $slides === [] )
		{
			$html .= '<p class="text-muted mb-0">No slides are listed yet.</p></div>';

			return $html;
		}

		$html .= match( $display )
		{
			CarouselDisplay::GALLERY => $this->renderGallery( $carousel, $slides, $id ),
			CarouselDisplay::LOGOS => $this->renderLogos( $carousel, $slides ),
			default => $this->renderSlider( $carousel, $slides, $id, $interval ),
		};

		$html .= '</div>';
		$html .= $this->styles();

		return $html;
	}

	/**
	 * @param CarouselSlide[] $slides
	 */
	private function renderSlider( Carousel $carousel, array $slides, string $id, int $interval ): string
	{
		$intervalAttr = $interval === 0 ? 'false' : (string) $interval;
		$count        = count( $slides );

		$html = '<div id="' . $this->esc( $id ) . '" class="carousel slide cms-carousel-slider" data-bs-ride="carousel" data-bs-pause="hover" data-bs-interval="' . $this->esc( $intervalAttr ) . '">';

		if( $count > 1 )
		{
			$html .= '<div class="carousel-indicators">';

			foreach( $slides as $index => $slide )
			{
				$active = $index === 0 ? ' class="active" aria-current="true"' : '';
				$html .= '<button type="button" data-bs-target="#' . $this->esc( $id ) . '" data-bs-slide-to="' . $index . '"' . $active . ' aria-label="Slide ' . ( $index + 1 ) . '"></button>';
			}

			$html .= '</div>';
		}

		$html .= '<div class="carousel-inner">';

		foreach( $slides as $index => $slide )
		{
			$active  = $index === 0 ? ' active' : '';
			$alt     = $this->esc( $slide->resolveAlt( $carousel->getName() ) );
			$heading = $slide->getHeading();
			$caption = $slide->getCaption();
			$link    = $slide->getLinkUrl();

			$html .= '<div class="carousel-item' . $active . '">';
			$html .= '<img src="' . $this->esc( $slide->getImageUrl() ) . '" class="d-block w-100 cms-carousel-slide-image" alt="' . $alt . '" loading="' . ( $index === 0 ? 'eager' : 'lazy' ) . '">';

			if( $heading || $caption || $link )
			{
				$html .= '<div class="carousel-caption d-none d-md-block">';

				if( $heading )
				{
					$html .= '<h5>' . $this->esc( $heading ) . '</h5>';
				}

				if( $caption )
				{
					$html .= '<p>' . nl2br( $this->esc( $caption ) ) . '</p>';
				}

				if( $link )
				{
					$label = $slide->getLinkLabel() ?: ( $heading ?: 'Learn more' );
					$html .= '<a class="btn btn-light btn-sm" href="' . $this->esc( $link ) . '">' . $this->esc( $label ) . '</a>';
				}

				$html .= '</div>';
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		if( $count > 1 )
		{
			$html .= '<button class="carousel-control-prev" type="button" data-bs-target="#' . $this->esc( $id ) . '" data-bs-slide="prev">';
			$html .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
			$html .= '<span class="visually-hidden">Previous</span>';
			$html .= '</button>';
			$html .= '<button class="carousel-control-next" type="button" data-bs-target="#' . $this->esc( $id ) . '" data-bs-slide="next">';
			$html .= '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
			$html .= '<span class="visually-hidden">Next</span>';
			$html .= '</button>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * @param CarouselSlide[] $slides
	 */
	private function renderGallery( Carousel $carousel, array $slides, string $id ): string
	{
		$modalId    = $id . '-lightbox';
		$carouselId = $id . '-lightbox-slides';

		$html = '<div class="row g-3 cms-carousel-gallery">';

		foreach( $slides as $index => $slide )
		{
			$alt = $this->esc( $slide->resolveAlt( $carousel->getName() ) );

			$html .= '<div class="col-6 col-md-4 col-lg-3">';
			$html .= '<button type="button" class="cms-carousel-gallery-thumb border-0 p-0 bg-transparent w-100" data-bs-toggle="modal" data-bs-target="#' . $this->esc( $modalId ) . '" data-cms-gallery-index="' . $index . '">';
			$html .= '<img src="' . $this->esc( $slide->getImageUrl() ) . '" class="img-fluid rounded cms-carousel-gallery-image" alt="' . $alt . '" loading="lazy">';
			$html .= '</button>';
			$html .= '</div>';
		}

		$html .= '</div>';

		$html .= '<div class="modal fade" id="' . $this->esc( $modalId ) . '" tabindex="-1" aria-hidden="true">';
		$html .= '<div class="modal-dialog modal-dialog-centered modal-xl">';
		$html .= '<div class="modal-content bg-dark">';
		$html .= '<div class="modal-header border-0">';
		$html .= '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>';
		$html .= '</div>';
		$html .= '<div class="modal-body p-0">';
		$html .= $this->renderSlider( $carousel, $slides, $carouselId, 0 );
		$html .= '</div></div></div></div>';

		$html .= '<script>';
		$html .= '(function(){';
		$html .= 'var modal=document.getElementById(' . json_encode( $modalId ) . ');';
		$html .= 'if(!modal){return;}';
		$html .= 'modal.addEventListener("show.bs.modal",function(event){';
		$html .= 'var trigger=event.relatedTarget;';
		$html .= 'if(!trigger){return;}';
		$html .= 'var index=parseInt(trigger.getAttribute("data-cms-gallery-index"),10)||0;';
		$html .= 'var el=document.getElementById(' . json_encode( $carouselId ) . ');';
		$html .= 'if(el&&window.bootstrap&&bootstrap.Carousel){bootstrap.Carousel.getOrCreateInstance(el).to(index);}';
		$html .= '});';
		$html .= '})();';
		$html .= '</script>';

		return $html;
	}

	/**
	 * @param CarouselSlide[] $slides
	 */
	private function renderLogos( Carousel $carousel, array $slides ): string
	{
		$html = '<div class="cms-carousel-logos d-flex flex-wrap align-items-center justify-content-center gap-4">';

		foreach( $slides as $slide )
		{
			$alt   = $this->esc( $slide->resolveAlt( $carousel->getName() ) );
			$image = '<img src="' . $this->esc( $slide->getImageUrl() ) . '" class="cms-carousel-logo" alt="' . $alt . '" loading="lazy">';
			$link  = $slide->getLinkUrl();

			if( $link )
			{
				$html .= '<a class="cms-carousel-logo-link" href="' . $this->esc( $link ) . '">' . $image . '</a>';
			}
			else
			{
				$html .= $image;
			}
		}

		$html .= '</div>';

		return $html;
	}

	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private function styles(): string
	{
		return '<style>'
			. '.cms-carousel-slide-image{object-fit:cover;max-height:520px;}'
			. '.cms-carousel-gallery-thumb{cursor:pointer;}'
			. '.cms-carousel-gallery-image{width:100%;aspect-ratio:4/3;object-fit:cover;}'
			. '.cms-carousel-logo{max-height:72px;max-width:160px;object-fit:contain;filter:grayscale(100%);opacity:.8;transition:filter .2s,opacity .2s;}'
			. '.cms-carousel-logo-link:hover .cms-carousel-logo,.cms-carousel-logo:hover{filter:none;opacity:1;}'
			. '</style>';
	}
}
