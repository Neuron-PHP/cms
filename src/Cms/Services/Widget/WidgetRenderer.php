<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Models\Post;

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

	public function __construct( ?IPostRepository $postRepository = null )
	{
		$this->_postRepository = $postRepository;
	}

	/**
	 * Render a widget by type
	 *
	 * @param string $widgetType Widget type name
	 * @param array $config Widget configuration/attributes
	 * @return string Rendered HTML
	 */
	public function render( string $widgetType, array $config ): string
	{
		return match( $widgetType )
		{
			'latest-posts' => $this->renderLatestPosts( $config ),
			'contact-form' => $this->renderContactForm( $config ),
			default => $this->renderUnknownWidget( $widgetType )
		};
	}

	/**
	 * Render latest posts widget
	 *
	 * Attributes:
	 * - category: Filter by category slug (optional)
	 * - limit: Number of posts to show (default: 5)
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
	 * Render contact form widget
	 *
	 * Attributes:
	 * - form_id: Form identifier (default: 'contact')
	 * - recipient: Email recipient (optional)
	 * - title: Form title (default: 'Contact Us')
	 */
	private function renderContactForm( array $config ): string
	{
		$formId = htmlspecialchars( $config['form_id'] ?? 'contact' );
		$recipient = htmlspecialchars( $config['recipient'] ?? '' );
		$title = htmlspecialchars( $config['title'] ?? 'Contact Us' );

		$html = "<div class='contact-form-widget'>\n";
		$html .= "  <h3 class='mb-4'>{$title}</h3>\n";
		$html .= "  <form method='POST' action='/contact/submit' class='needs-validation'>\n";
		$html .= "    <input type='hidden' name='form_id' value='{$formId}'>\n";

		if( $recipient )
		{
			$html .= "    <input type='hidden' name='recipient' value='{$recipient}'>\n";
		}

		$html .= "    <div class='mb-3'>\n";
		$html .= "      <label for='name' class='form-label'>Name</label>\n";
		$html .= "      <input type='text' id='name' name='name' class='form-control' required>\n";
		$html .= "    </div>\n";

		$html .= "    <div class='mb-3'>\n";
		$html .= "      <label for='email' class='form-label'>Email</label>\n";
		$html .= "      <input type='email' id='email' name='email' class='form-control' required>\n";
		$html .= "    </div>\n";

		$html .= "    <div class='mb-3'>\n";
		$html .= "      <label for='message' class='form-label'>Message</label>\n";
		$html .= "      <textarea id='message' name='message' class='form-control' rows='5' required></textarea>\n";
		$html .= "    </div>\n";

		$html .= "    <button type='submit' class='btn btn-primary'>Send Message</button>\n";
		$html .= "  </form>\n";
		$html .= "</div>\n";

		return $html;
	}

	/**
	 * Render unknown widget placeholder
	 */
	private function renderUnknownWidget( string $widgetType ): string
	{
		return "<!-- Unknown widget: {$widgetType} -->";
	}
}
