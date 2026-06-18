<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\DatabaseEventRepository;

/**
 * Featured event widget for displaying the next available featured event.
 *
 * Renders a single card with the details of the next published featured event
 * that has not yet ended. If no featured event is available it renders nothing
 * visible (an HTML comment), so it is safe to place in any page or post.
 *
 * Display modes:
 * - [featured-event]                       Full card (image, title, date, etc.)
 * - [featured-event display="image"]       Just the featured image, linked to
 *                                          the event. Useful for sponsored/event
 *                                          banners (e.g. a cover-photo strip).
 * - [featured-event display="image" link="false"]  Image only, no link.
 *
 * @package Neuron\Cms\Services\Widget
 */
class FeaturedEventWidget implements IWidget
{
	private DatabaseEventRepository $_eventRepository;

	public function __construct( DatabaseEventRepository $eventRepository )
	{
		$this->_eventRepository = $eventRepository;
	}

	/**
	 * Get widget shortcode name
	 */
	public function getName(): string
	{
		return 'featured-event';
	}

	/**
	 * Render the featured event widget
	 *
	 * @param array<string, mixed> $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string
	{
		$event = $this->_eventRepository->getNextFeatured( 'published' );

		if( !$event )
		{
			return '<!-- Featured event widget: no featured event available -->';
		}

		$display = strtolower( (string)( $attrs['display'] ?? 'card' ) );

		if( $display === 'image' )
		{
			$link = !isset( $attrs['link'] )
				|| filter_var( $attrs['link'], FILTER_VALIDATE_BOOLEAN );

			return $this->renderImage( $event, $link );
		}

		return $this->renderTemplate( $event );
	}

	/**
	 * Get widget description
	 */
	public function getDescription(): string
	{
		return 'Display the next available featured event';
	}

	/**
	 * Get supported attributes
	 *
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'display' => 'Layout to render: "card" (default, full details) or "image" (featured image only)',
			'link'    => 'When display="image", whether the image links to the event page (default: true)',
		];
	}

	/**
	 * Render only the featured image for the event.
	 *
	 * Returns an HTML comment (renders nothing visible) when the featured event
	 * has no image, so callers can safely fall back to their own placeholder.
	 *
	 * @param Event $event
	 * @param bool  $link Wrap the image in a link to the event page
	 * @return string
	 */
	private function renderImage( Event $event, bool $link = true ): string
	{
		$image = $event->getFeaturedImage();

		if( !$image )
		{
			return '<!-- Featured event widget: featured event has no image -->';
		}

		$title = htmlspecialchars( $event->getTitle(), ENT_QUOTES, 'UTF-8' );
		$src   = htmlspecialchars( $image, ENT_QUOTES, 'UTF-8' );

		$img = '<img class="featured-event-image-only" src="' . $src . '" alt="' . $title . '">';

		if( !$link )
		{
			return $img;
		}

		[ 'href' => $href, 'attrs' => $attrs, 'external' => $external ] = $this->resolveLink( $event );
		$extraClass = $external ? ' featured-event-image-link--external' : '';

		return '<a class="featured-event-image-link' . $extraClass . '" href="' . $href . '"' . $attrs . '>' . $img . '</a>';
	}

	/**
	 * Resolve the link target for the event.
	 *
	 * When the event has an external URL it is preferred and opened in a new
	 * tab; otherwise the internal event page is used.
	 *
	 * @param Event $event
	 * @return array{href: string, attrs: string, external: bool}
	 */
	private function resolveLink( Event $event ): array
	{
		if( $event->hasExternalUrl() )
		{
			return [
				'href'     => htmlspecialchars( $event->getExternalUrl(), ENT_QUOTES, 'UTF-8' ),
				'attrs'    => ' target="_blank" rel="noopener noreferrer"',
				'external' => true,
			];
		}

		$slug = htmlspecialchars( $event->getSlug(), ENT_QUOTES, 'UTF-8' );

		return [
			'href'     => '/calendar/event/' . $slug,
			'attrs'    => '',
			'external' => false,
		];
	}

	/**
	 * Render widget template
	 *
	 * @param Event $event
	 * @return string
	 */
	private function renderTemplate( Event $event ): string
	{
		$title = htmlspecialchars( $event->getTitle(), ENT_QUOTES, 'UTF-8' );
		$slug = htmlspecialchars( $event->getSlug(), ENT_QUOTES, 'UTF-8' );

		$startDate = $event->getStartDate()->format( 'F j, Y' );
		if( !$event->isAllDay() )
		{
			$startDate .= ' at ' . $event->getStartDate()->format( 'g:i A' );
		}

		[ 'href' => $href, 'attrs' => $attrs, 'external' => $external ] = $this->resolveLink( $event );

		// The whole card is a single link. When the event is managed elsewhere it
		// opens that site in a new tab; otherwise it goes to the internal page.
		$widgetClass = 'featured-event-widget' . ( $external ? ' featured-event-widget--external' : '' );

		$html = '<a class="' . $widgetClass . '" href="' . $href . '"' . $attrs . '>';

		$image = $event->getFeaturedImage();
		if( $image )
		{
			$html .= '<div class="featured-event-image">';
			$html .= '<img src="' . htmlspecialchars( $image, ENT_QUOTES, 'UTF-8' ) . '" alt="' . $title . '">';
			$html .= '</div>';
		}

		$html .= '<div class="featured-event-body">';
		$html .= '<span class="featured-event-badge">Featured Event</span>';
		if( $external )
		{
			$html .= '<span class="featured-event-external" aria-label="Opens external site in a new tab">External &#8599;</span>';
		}
		$html .= '<h3 class="featured-event-title">' . $title . '</h3>';
		$html .= '<p class="featured-event-date">' . $startDate . '</p>';

		$location = $event->getLocation();
		if( $location )
		{
			$html .= '<p class="featured-event-location">' . htmlspecialchars( $location, ENT_QUOTES, 'UTF-8' ) . '</p>';
		}

		$description = $event->getDescription();
		if( $description )
		{
			$html .= '<p class="featured-event-description">' . htmlspecialchars( $description, ENT_QUOTES, 'UTF-8' ) . '</p>';
		}

		$linkLabel = $external ? 'Visit event site &#8599;' : 'View details';
		$html .= '<span class="featured-event-link">' . $linkLabel . '</span>';
		$html .= '</div>';
		$html .= '</a>';

		return $html;
	}
}
