<?php
namespace Neuron\Cms\Controllers;

/**
 * Base content controller for the Neuron CMS framework.
 * 
 * This abstract controller provides foundational functionality for all CMS
 * content types, including site configuration management, version tracking,
 * SEO metadata handling, and common rendering operations. It serves as the
 * base class for specialized controllers like Blog, Pages, and other content types.
 * 
 * Key features:
 * - Site configuration and metadata management
 * - Version information loading and tracking
 * - RSS feed URL configuration and management
 * - Registry-based settings integration
 * - Fluent interface for property configuration
 * - Markdown rendering support for content pages
 * - SEO-friendly title and description handling
 * - Base URL and canonical URL management
 * 
 * The controller automatically loads site settings from the registry and
 * configures common CMS properties, making it easy for derived controllers
 * to focus on content-specific functionality while inheriting consistent
 * site-wide configuration and behavior.
 * 
 * @package Neuron\Cms
 * 
 * @example
 * ```php
 * // Custom content controller extending base
 * class PageController extends Content
 * {
 *     public function show(array $params): string
 *     {
 *         return $this->renderHtml(
 *             HttpResponseStatus::OK,
 *             [
 *                 'Title' => $this->getTitle() . ' | ' . $this->getName(),
 *                 'Description' => $this->getDescription(),
 *                 'Content' => $pageContent
 *             ],
 *             'page'
 *         );
 *     }
 * }
 * ```
 */

use Neuron\Data\Object\Version;
use Neuron\Mvc\Application;
use Neuron\Mvc\Controllers\Base;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

class Content extends Base
{
	private string $_name = 'Blahg';
	private string $_title = 'Blahg';
	private string $_description = '';
	private string $_url = 'example.com/bog';
	private string $_rssUrl = 'example.com/blog/rss';

	/**
	 * @param Application $app
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		$settings = Registry::getInstance()->get( 'Settings' );

		$this->setName( $settings->get( 'site', 'name' ) ?? 'Neuron CMS' )
			  ->setTitle( $settings->get( 'site', 'title' ) ?? 'Neuron CMS' )
			  ->setDescription( $settings->get( 'site', 'description' ) ?? '' )
			  ->setUrl( $settings->get( 'site', 'url' ) ?? '' )
			  ->setRssUrl($this->getUrl() . "/blog/rss" );

		try
		{
			$version = new Version();
			$version->loadFromFile( "../.version.json" );

			Registry::getInstance()->set( 'version', 'v'.$version->getAsString() );
		}
		catch( \Exception $e )
		{
			Registry::getInstance()->set( 'version', 'v0.0.0' );
		}

		Registry::getInstance()->set( 'name', $this->getName() );
		Registry::getInstance()->set( 'rss_url', $this->getRssUrl() );
	}

	/**
	 * Getters and Setters
	 */
	public function getRssUrl(): string
	{
		return $this->_rssUrl;
	}

	/**
	 * Set the RSS URL for the site.
	 *
	 * @param string $rssUrl
	 * @return Content
	 */
	public function setRssUrl( string $rssUrl ): Content
	{
		$this->_rssUrl = $rssUrl;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->_name;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function setName( string $name ): Content
	{
		$this->_name = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->_title;
	}

	/**
	 * @param string $title
	 * @return $this
	 */
	public function setTitle( string $title ): Content
	{
		$this->_title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->_description;
	}

	/**
	 * @param string $description
	 * @return $this
	 */
	public function setDescription( string $description ): Content
	{
		$this->_description = $description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->_url;
	}

	/**
	 * Set the URL for the site.
	 *
	 * @param string $url
	 * @return Content
	 */
	public function setUrl( string $url ): Content
	{
		$this->_url = $url;
		return $this;
	}

	/**
	 * @throws \Neuron\Core\Exceptions\NotFound
	 * @throws \League\CommonMark\Exception\CommonMarkException
	 */
	public function markdown( array $parameters ): string
	{
		$viewData = array();

		$title = $parameters[ 'page' ];

		$title = str_replace( '-', ' ', $title );
		$title = ucwords( $title );

		$viewData[ 'Title' ]		= $this->getName() . ' | ' . $this->getTitle();

		return $this->renderMarkdown(
			HttpResponseStatus::OK,
			$viewData,
			$parameters[ 'page' ]
		);
	}
}
