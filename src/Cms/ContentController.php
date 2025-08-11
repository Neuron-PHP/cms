<?php
namespace Neuron\Cms;

use Neuron\Data\Object\Version;
use Neuron\Mvc\Controllers\Base;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

class ContentController extends Base
{
	private string $_Name = 'Blahg';
	private string $_Title = 'Blahg';
	private string $_Description = '';
	private string $_Url = 'example.com/bog';
	private string $_RssUrl = 'example.com/blog/rss';

	/**
	 * SiteController constructor.
	 *
	 * @param $Router
	 * @throws \Exception
	 */
	public function __construct( $Router )
	{
		parent::__construct( $Router );

		$Settings = Registry::getInstance()->get( 'Settings' );

		$this->setName( $Settings->get( 'site', 'name' ) )
			  ->setTitle( $Settings->get( 'site', 'title' ) )
			  ->setDescription( $Settings->get( 'site', 'description' ) )
			  ->setUrl( $Settings->get( 'site', 'url' ) )
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
	 * @return ContentController
	 */
	public function setRssUrl( string $RssUrl ): ContentController
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
	public function setName( string $Name ): ContentController
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
	public function setTitle( string $Title ): ContentController
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
	public function setDescription( string $Description ): ContentController
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
	 * @return ContentController
	 */
	public function setUrl( string $Url ): ContentController
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
