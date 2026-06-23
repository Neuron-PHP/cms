<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Repositories\IProductRepository;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Cms\Services\Store\CartService;
use Neuron\Cms\Services\Store\StoreService;
use Neuron\Cms\Models\Post;
use Neuron\Data\Settings\SettingManager;

/**
 * Renders built-in widgets.
 *
 * This class provides implementations for common CMS widgets.
 * Custom widgets should be implemented as Widget classes and registered
 * with the WidgetRegistry.
 *
 * @package Neuron\Cms\Services\Widget
 */
class WidgetRenderer
{
	private ?IPostRepository $_postRepository = null;
	private ?IEventRepository $_eventRepository = null;
	private ?IEventCategoryRepository $_eventCategoryRepository = null;
	private ?IEventRegistrationRepository $_eventRegistrationRepository = null;
	private ?SettingManager $_settings = null;
	private ?IProductRepository $_productRepository = null;

	public function __construct(
		?IPostRepository $postRepository = null,
		?IEventRepository $eventRepository = null,
		?IEventCategoryRepository $eventCategoryRepository = null,
		?SettingManager $settings = null,
		?IEventRegistrationRepository $eventRegistrationRepository = null,
		?IProductRepository $productRepository = null
	)
	{
		$this->_postRepository = $postRepository;
		$this->_eventRepository = $eventRepository;
		$this->_eventCategoryRepository = $eventCategoryRepository;
		$this->_settings = $settings;
		$this->_eventRegistrationRepository = $eventRegistrationRepository;
		$this->_productRepository = $productRepository;
	}

	/**
	 * Render a widget by type
	 *
	 * @param string $widgetType Widget type name
	 * @param array<string, mixed> $config Widget configuration/attributes
	 * @return string Rendered HTML
	 */
	public function render( string $widgetType, array $config ): string
	{
		return match( $widgetType )
		{
			'latest-posts' => $this->renderLatestPosts( $config ),
			'calendar' => $this->renderCalendar( $config ),
			'featured-event' => $this->renderFeaturedEvent( $config ),
			'event-registration' => $this->renderEventRegistration( $config ),
			'contact' => $this->renderContact( $config ),
			'payment', 'donation' => $this->renderPayment( $config ),
			'products' => $this->renderStore( 'products', $config ),
			'product' => $this->renderStore( 'product', $config ),
			'cart' => $this->renderStore( 'cart', $config ),
			default => $this->renderUnknownWidget( $widgetType )
		};
	}

	/**
	 * Render latest posts widget
	 *
	 * Attributes:
	 * - category: Filter by category slug (optional)
	 * - limit: Number of posts to show (default: 5)
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderLatestPosts( array $config ): string
	{
		if( !$this->_postRepository )
		{
			return "<!-- Latest posts widget requires PostRepository -->";
		}

		$limit = $config['limit'] ?? 5;
		$posts = $this->_postRepository->getPublished( $limit );

		if( empty( $posts ) )
		{
			return "<div class='latest-posts-widget'><p class='text-muted'>No posts available</p></div>";
		}

		$html = "<div class='latest-posts-widget'>\n";
		$html .= "  <h3 class='mb-4'>Latest Posts</h3>\n";
		$html .= "  <div class='post-list'>\n";

		foreach( $posts as $post )
		{
			$title = htmlspecialchars( $post->getTitle() );
			$slug = htmlspecialchars( $post->getSlug() );
			$excerpt = htmlspecialchars( $post->getExcerpt() ?? '' );
			$date = $post->getPublishedAt() ? $post->getPublishedAt()->format( 'F j, Y' ) : '';

			$html .= "    <article class='post-item mb-4 pb-4 border-bottom'>\n";
			$html .= "      <h4 class='h5'>\n";
			$html .= "        <a href='/blog/article/{$slug}' class='text-decoration-none'>{$title}</a>\n";
			$html .= "      </h4>\n";
			if( $date )
			{
				$html .= "      <p class='text-muted small mb-2'>{$date}</p>\n";
			}
			if( $excerpt )
			{
				$html .= "      <p class='mb-0'>{$excerpt}</p>\n";
			}
			$html .= "    </article>\n";
		}

		$html .= "  </div>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Render calendar widget
	 *
	 * Attributes:
	 * - category: Filter by category slug (optional)
	 * - limit: Number of events to show (default: 5)
	 * - upcoming: Show upcoming events (true) or past events (false) (default: true)
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderCalendar( array $config ): string
	{
		if( !$this->_eventRepository || !$this->_eventCategoryRepository )
		{
			return "<!-- Calendar widget requires EventRepository and EventCategoryRepository -->";
		}

		// CalendarWidget requires concrete database repositories - cast from interfaces
		if( !($this->_eventRepository instanceof \Neuron\Cms\Repositories\DatabaseEventRepository) ||
			!($this->_eventCategoryRepository instanceof \Neuron\Cms\Repositories\DatabaseEventCategoryRepository) )
		{
			return "<!-- Calendar widget requires DatabaseEventRepository and DatabaseEventCategoryRepository -->";
		}

		$widget = new CalendarWidget( $this->_eventRepository, $this->_eventCategoryRepository );
		return $widget->render( $config );
	}

	/**
	 * Render featured event widget
	 *
	 * Renders the next available featured event. Takes no attributes.
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderFeaturedEvent( array $config ): string
	{
		if( !$this->_eventRepository )
		{
			return "<!-- Featured event widget requires EventRepository -->";
		}

		// FeaturedEventWidget requires the concrete database repository - cast from interface
		if( !($this->_eventRepository instanceof \Neuron\Cms\Repositories\DatabaseEventRepository) )
		{
			return "<!-- Featured event widget requires DatabaseEventRepository -->";
		}

		$widget = new FeaturedEventWidget( $this->_eventRepository );
		return $widget->render( $config );
	}

	/**
	 * Render event registration widget
	 *
	 * Attributes:
	 * - event: Event slug for single-event registration
	 * - category: Event category slug to offer the next upcoming events of that type
	 * - limit: Number of upcoming dates to offer in category mode (default: 3)
	 * - title: Optional heading override
	 * - button: Optional submit button label override
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderEventRegistration( array $config ): string
	{
		if( !$this->_eventRepository || !$this->_eventCategoryRepository )
		{
			return "<!-- Event registration widget requires EventRepository and EventCategoryRepository -->";
		}

		$widget = new EventRegistrationWidget(
			$this->_eventRepository,
			$this->_eventCategoryRepository,
			$this->_eventRegistrationRepository
		);

		return $widget->render( $config );
	}

	/**
	 * Render contact form widget
	 *
	 * Attributes:
	 * - form: Contact form key from config (default: configured default_form)
	 * - title: Optional heading override
	 * - button: Optional submit button label override
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderContact( array $config ): string
	{
		if( !$this->_settings )
		{
			return "<!-- Contact widget requires SettingManager -->";
		}

		$widget = new ContactFormWidget( new ContactService( $this->_settings ) );

		return $widget->render( $config );
	}

	/**
	 * Render payment / donation form widget
	 *
	 * Attributes:
	 * - form: Payment form key from config (default: configured default_form)
	 * - title: Optional heading override
	 * - button: Optional submit button label override
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderPayment( array $config ): string
	{
		if( !$this->_settings )
		{
			return "<!-- Payment widget requires SettingManager -->";
		}

		$widget = new PaymentWidget( new PaymentService( $this->_settings ) );

		return $widget->render( $config );
	}

	/**
	 * Render a storefront widget ( products grid, single product, or cart link )
	 *
	 * Attributes ( products ): limit, title
	 * Attributes ( product ): id or slug
	 * Attributes ( cart ): label
	 *
	 * @param string $mode One of 'products', 'product', 'cart'
	 * @param array<string, mixed> $config
	 * @return string
	 */
	private function renderStore( string $mode, array $config ): string
	{
		if( !$this->_settings || !$this->_productRepository )
		{
			return "<!-- Store widget requires SettingManager and ProductRepository -->";
		}

		$widget = new StoreWidget(
			$this->_productRepository,
			new StoreService( $this->_settings ),
			new CartService( $this->_productRepository )
		);

		return match( $mode )
		{
			'product' => $widget->renderProduct( $config ),
			'cart'    => $widget->renderCart( $config ),
			default   => $widget->renderProducts( $config )
		};
	}

	/**
	 * Render unknown widget placeholder
	 */
	private function renderUnknownWidget( string $widgetType ): string
	{
		return "<!-- Unknown widget: {$widgetType} -->";
	}
}
