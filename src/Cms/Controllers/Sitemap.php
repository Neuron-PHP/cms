<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Get;

/**
 * XML sitemap of published public content.
 *
 * @package Neuron\Cms\Controllers
 */
class Sitemap extends Content
{
	private IPageRepository $_pages;
	private IPostRepository $_posts;
	private IEventRepository $_events;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPageRepository $pages,
		IPostRepository $posts,
		IEventRepository $events
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_pages = $pages;
		$this->_posts = $posts;
		$this->_events = $events;
	}

	#[Get('/sitemap.xml', name: 'sitemap')]
	public function index( Request $request ): never
	{
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo $this->document();
		exit;
	}

	public function document(): string
	{
		$urls = [];
		$urls[] = $this->urlNode( $this->absoluteRoute( 'home' ), '1.0', 'daily' );
		$urls[] = $this->urlNode( $this->absoluteRoute( 'blog' ), '0.8', 'daily' );
		$urls[] = $this->urlNode( $this->absoluteRoute( 'calendar' ), '0.7', 'weekly' );
		$urls[] = $this->urlNode( $this->absoluteRoute( 'contact' ), '0.5', 'monthly' );

		foreach( $this->_pages->getPublished() as $page )
		{
			$urls[] = $this->urlNode(
				$this->absoluteRoute( 'page', [ 'slug' => $page->getSlug() ] ),
				'0.7',
				'weekly',
				$page->getUpdatedAt()?->format( 'Y-m-d' ) ?? $page->getPublishedAt()?->format( 'Y-m-d' )
			);
		}

		foreach( $this->_posts->getPublished() as $post )
		{
			$urls[] = $this->urlNode(
				$this->absoluteRoute( 'blog_post', [ 'slug' => $post->getSlug() ] ),
				'0.6',
				'weekly',
				$post->getUpdatedAt()?->format( 'Y-m-d' ) ?? $post->getPublishedAt()?->format( 'Y-m-d' )
			);
		}

		foreach( $this->_events->all() as $event )
		{
			if( !$event->isPublished() )
			{
				continue;
			}

			$urls[] = $this->urlNode(
				$this->absoluteRoute( 'calendar_event', [ 'slug' => $event->getSlug() ] ),
				'0.6',
				'weekly'
			);
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$xml .= implode( '', $urls );
		$xml .= '</urlset>';

		return $xml;
	}

	private function urlNode( string $loc, string $priority, string $changefreq, ?string $lastmod = null ): string
	{
		if( $loc === '' )
		{
			return '';
		}

		$xml = "  <url>\n";
		$xml .= '    <loc>' . htmlspecialchars( $loc ) . "</loc>\n";

		if( $lastmod )
		{
			$xml .= '    <lastmod>' . htmlspecialchars( $lastmod ) . "</lastmod>\n";
		}

		$xml .= '    <changefreq>' . htmlspecialchars( $changefreq ) . "</changefreq>\n";
		$xml .= '    <priority>' . htmlspecialchars( $priority ) . "</priority>\n";
		$xml .= "  </url>\n";

		return $xml;
	}
}
