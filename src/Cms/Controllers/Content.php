<?php
namespace Neuron\Cms\Controllers;
use Neuron\Data\Factories;

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

use League\CommonMark\Exception\CommonMarkException;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Objects\Version;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Controllers\Base;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

class Content extends Base
{
	private string $_name = 'Blahg';
	private string $_title = 'Blahg';
	private string $_description = '';
	private string $_url = 'example.com/bog';
	private string $_rssUrl = 'example.com/blog/rss';
	protected SessionManager $_sessionManager;
	protected SettingManager $_settings;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager
	)
	{
		parent::__construct( $app );

		$this->_settings = $settings;
		$this->_sessionManager = $sessionManager;

		$this->setName( $this->_settings->get( 'site', 'name' ) ?? 'Neuron CMS' )
			  ->setTitle( $this->_settings->get( 'site', 'title' ) ?? 'Neuron CMS' )
			  ->setDescription( $this->_settings->get( 'site', 'description' ) ?? '' )
			  ->setUrl( $this->_settings->get( 'site', 'url' ) ?? '' )
			  ->setRssUrl($this->getUrl() . "/blog/rss" );

		// Note: Registry is intentionally used here as a view data bag for global template variables.
		// These values are accessed by templates throughout the application.
		// Future improvement: Consider using a dedicated ViewContext service instead.
		try
		{
			$versionFilePath = $this->_settings->get( 'paths', 'version_file' ) ?? "../.version.json";
			$version = Factories\Version::fromFile( $versionFilePath );
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
	 * Render a markdown page.
	 *
	 * @param Request $request
	 * @throws NotFound
	 * @throws CommonMarkException
	 */
	public function markdown( Request $request ): string
	{
		$viewData = array();

		$page = $request->getRouteParameter( 'page' ) ?? 'index';

		$viewData[ 'Title' ]		= $this->getName() . ' | ' . $this->getTitle();

		return $this->renderMarkdown(
			HttpResponseStatus::OK,
			$viewData,
			$page
		);
	}

	/**
	 * Get the session manager and ensure session is started.
	 *
	 * @return SessionManager
	 */
	protected function getSessionManager(): SessionManager
	{
		// Lazy-load if not injected (for backward compatibility)
		if( !$this->_sessionManager )
		{
			$this->_sessionManager = new SessionManager();
		}

		if( !$this->_sessionManager->isStarted() )
		{
			$this->_sessionManager->start();
		}

		return $this->_sessionManager;
	}

	/**
	 * Redirect to a named route with optional flash message.
	 *
	 * @param string $routeName The name of the route to redirect to
	 * @param array<string, mixed> $parameters Route parameters
	 * @param array{0: string, 1: string}|null $flash Optional flash message as [$type, $message]
	 * @return never
	 */
	protected function redirect( string $routeName, array $parameters = [], ?array $flash = null ): never
	{
		if( $flash )
		{
			[ $type, $message ] = $flash;
			$this->getSessionManager()->flash( $type, $message );
		}

		$url = $this->urlFor( $routeName, $parameters ) ?? '/';
		header( 'Location: ' . $url );
		exit;
	}

	/**
	 * Redirect to a URL path with optional flash message.
	 *
	 * @param string $url The URL path to redirect to
	 * @param array{0: string, 1: string}|null $flash Optional flash message as [$type, $message]
	 * @return never
	 */
	protected function redirectToUrl( string $url, ?array $flash = null ): never
	{
		if( $flash )
		{
			[ $type, $message ] = $flash;
			$this->getSessionManager()->flash( $type, $message );
		}

		header( 'Location: ' . $url );
		exit;
	}

	/**
	 * Redirect back to the previous page or a fallback URL.
	 *
	 * @param string $fallback Fallback URL if referer is not available
	 * @param array{0: string, 1: string}|null $flash Optional flash message as [$type, $message]
	 * @return never
	 */
	protected function redirectBack( string $fallback = '/', ?array $flash = null ): never
	{
		if( $flash )
		{
			[ $type, $message ] = $flash;
			$this->getSessionManager()->flash( $type, $message );
		}
		$url = $_SERVER['HTTP_REFERER'] ?? $fallback;
		header( 'Location: ' . $url );
		exit;
	}

	/**
	 * Set a flash message for the next request.
	 *
	 * @param string $type Message type (success, error, warning, info)
	 * @param string $message The message text
	 * @return void
	 */
	protected function flash( string $type, string $message ): void
	{
		$this->getSessionManager()->flash( $type, $message );
	}

	/**
	 * Initialize CSRF token and store in Registry for template access.
	 * Should be called by controllers that render forms requiring CSRF protection.
	 *
	 * Note: Registry is used here as a view data bag to make CSRF tokens available to templates.
	 *
	 * @return void
	 */
	protected function initializeCsrfToken(): void
	{
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );
	}

	/**
	 * Create a DTO from a YAML configuration file.
	 *
	 * @param string $config Path to YAML config file relative to Dtos/ (e.g., 'auth/login-request.yaml')
	 * @return \Neuron\Dto\Dto
	 * @throws \Exception If DTO factory fails
	 */
	protected function createDto( string $config ): \Neuron\Dto\Dto
	{
		$configPath = __DIR__ . '/../Dtos/' . $config;

		if( !file_exists( $configPath ) )
		{
			throw new \Exception( "DTO configuration file not found: {$configPath}" );
		}

		$factory = new \Neuron\Dto\Factory( $configPath );
		return $factory->create();
	}

	/**
	 * Map HTTP request data to a DTO.
	 *
	 * @param \Neuron\Dto\Dto $dto The DTO to populate
	 * @param Request $request The HTTP request containing form data
	 * @return void
	 */
	protected function mapRequestToDto( \Neuron\Dto\Dto $dto, Request $request ): void
	{
		foreach( $dto->getProperties() as $name => $property )
		{
			$value = $request->post( $name, null );

			if( $value !== null )
			{
				$dto->$name = $value;
			}
		}
	}

	/**
	 * Handle validation errors by redirecting with flash message.
	 *
	 * @param string $route Route name to redirect to
	 * @param array<string, array<int, string>> $errors Validation errors from DTO
	 * @param array<string, mixed> $routeParams Optional route parameters
	 * @return never
	 */
	protected function validationError( string $route, array $errors, array $routeParams = [] ): never
	{
		$errorMessage = 'Validation failed: ' . implode( ', ', array_map(
			fn( $field, $fieldErrors ) => $field . ': ' . implode( ', ', $fieldErrors ),
			array_keys( $errors ),
			array_values( $errors )
		));

		$this->redirect( $route, $routeParams, [\Neuron\Cms\Enums\FlashMessageType::ERROR->value, $errorMessage] );
	}
}
