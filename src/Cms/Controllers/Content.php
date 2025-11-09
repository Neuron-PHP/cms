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
	private string $_Name = 'Blahg';
	private string $_Title = 'Blahg';
	private string $_Description = '';
	private string $_Url = 'example.com/bog';
	private string $_RssUrl = 'example.com/blog/rss';

	/**
	 * @param Application $app
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		$Settings = Registry::getInstance()->get( 'Settings' );

		$this->setName( $Settings->get( 'site', 'name' ) ?? 'Neuron CMS' )
			  ->setTitle( $Settings->get( 'site', 'title' ) ?? 'Neuron CMS' )
			  ->setDescription( $Settings->get( 'site', 'description' ) ?? '' )
			  ->setUrl( $Settings->get( 'site', 'url' ) ?? '' )
			  ->setRssUrl($this->getUrl() . "/blog/rss" );

		try
		{
			$Version = new Version();
			$Version->loadFromFile( "../.version.json" );

			Registry::getInstance()->set( 'version', 'v'.$Version->getAsString() );
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
		return $this->_RssUrl;
	}

	/**
	 * Set the RSS URL for the site.
	 *
	 * @param string $RssUrl
	 * @return Content
	 */
	public function setRssUrl( string $RssUrl ): Content
	{
		$this->_RssUrl = $RssUrl;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->_Name;
	}

	/**
	 * @param string $Name
	 * @return $this
	 */
	public function setName( string $Name ): Content
	{
		$this->_Name = $Name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->_Title;
	}

	/**
	 * @param string $Title
	 * @return $this
	 */
	public function setTitle( string $Title ): Content
	{
		$this->_Title = $Title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->_Description;
	}

	/**
	 * @param string $Description
	 * @return $this
	 */
	public function setDescription( string $Description ): Content
	{
		$this->_Description = $Description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->_Url;
	}

	/**
	 * Set the URL for the site.
	 *
	 * @param string $Url
	 * @return Content
	 */
	public function setUrl( string $Url ): Content
	{
		$this->_Url = $Url;
		return $this;
	}

	/**
	 * @throws \Neuron\Core\Exceptions\NotFound
	 * @throws \League\CommonMark\Exception\CommonMarkException
	 */
	public function markdown( array $Parameters ): string
	{
		$ViewData = array();

		$Title = $Parameters[ 'page' ];

		$Title = str_replace( '-', ' ', $Title );
		$Title = ucwords( $Title );

		$ViewData[ 'Title' ]		= $this->getName() . ' | ' . $this->getTitle();

		return $this->renderMarkdown(
			HttpResponseStatus::OK,
			$ViewData,
			$Parameters[ 'page' ]
		);
	}
}
