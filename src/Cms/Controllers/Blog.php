<?php
namespace Neuron\Cms\Controllers;

/**
 * Blog management controller for the Neuron CMS framework.
 *
 * This controller provides comprehensive blog functionality including article
 * management, categorization, tagging, author filtering, and RSS feed generation.
 * It extends the base Content controller to leverage common CMS functionality
 * while adding blog-specific features and content organization.
 *
 * Key features:
 * - Article listing with pagination and filtering
 * - Category and tag-based content organization
 * - Author-specific article filtering
 * - SEO-friendly URL routing and slugs
 * - RSS/Atom feed generation for syndication
 * - Draft mode support for content preview
 * - Exception handling for missing articles
 * - Responsive HTML rendering with metadata
 *
 * The controller integrates with the database-backed post repository system
 * for content storage and retrieval, supporting database-driven article
 * management with full CRUD capabilities.
 *
 * @package Neuron\Cms
 */

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Data\Filter\Get;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;

class Blog extends Content
{
	private DatabasePostRepository $_PostRepository;
	private DatabaseCategoryRepository $_CategoryRepository;
	private DatabaseTagRepository $_TagRepository;
	private bool $_ShowDrafts = false;

	/**
	 * @param Application $app
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get database config from settings
		$Settings = Registry::getInstance()->get( 'Settings' );
		$dbConfig = $this->getDatabaseConfig( $Settings );

		if( !$dbConfig )
		{
			throw new \RuntimeException( 'Database configuration not found' );
		}

		// Initialize repositories
		$this->_PostRepository = new DatabasePostRepository( $dbConfig );
		$this->_CategoryRepository = new DatabaseCategoryRepository( $dbConfig );
		$this->_TagRepository = new DatabaseTagRepository( $dbConfig );

		// Check for drafts parameter
		$Get = new Get();
		$this->_ShowDrafts = $Get->filterScalar( 'drafts' ) ? true : false;
	}

	public function index( array $Parameters, ?Request $Request ): string
	{
		// Get published posts (or all if drafts mode)
		if( $this->_ShowDrafts )
		{
			$Posts = $this->_PostRepository->all();
		}
		else
		{
			$Posts = $this->_PostRepository->getPublished();
		}

		$Categories = $this->_CategoryRepository->all();
		$Tags = $this->_TagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Posts'       => $Posts,
				'Categories' => $Categories,
				'Tags'        => $Tags,
				'Title'       => $this->getTitle() . ' | ' . $this->getName(),
				'Description' => $this->getDescription(),
			],
			'index'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function show( array $Parameters, ?Request $Request ): string
	{
		$slug = $Parameters['title'];
		$Post = $this->_PostRepository->findBySlug( $slug );

		if( !$Post )
		{
			// Create error post
			$Post = new Post();
			$Post->setTitle( 'Article Not Found' );
			$Post->setBody( 'The requested article does not exist.' );
			$Post->setSlug( $slug );
		}
		elseif( !$Post->isPublished() && !$this->_ShowDrafts )
		{
			// Don't show drafts unless in draft mode
			$Post = new Post();
			$Post->setTitle( 'Article Not Available' );
			$Post->setBody( 'This article is not yet published.' );
			$Post->setSlug( $slug );
		}
		else
		{
			// Increment view count for published posts
			if( $Post->isPublished() )
			{
				$this->_PostRepository->incrementViewCount( $Post->getId() );
			}
		}

		$Categories = $this->_CategoryRepository->all();
		$Tags = $this->_TagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $Categories,
				'Tags'        => $Tags,
				'Post'        => $Post,
				'Title'       => $Post->getTitle() . ' | ' . $this->getName()
			],
			'show'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function author( array $Parameters, ?Request $Request ): string
	{
		$authorName = $Parameters['author'];

		// Note: This would need a user lookup by username
		// For now, we'll just show an empty list
		$Posts = [];

		$Categories = $this->_CategoryRepository->all();
		$Tags = $this->_TagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $Categories,
				'Tags'        => $Tags,
				'Posts'       => $Posts,
				'Title'       => "Articles by $authorName | " . $this->getName(),
				'Author'      => $authorName
			],
			'index'
		);
	}


	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function tag( array $Parameters, ?Request $Request ): string
	{
		$tagSlug = $Parameters['tag'];
		$Tag = $this->_TagRepository->findBySlug( $tagSlug );

		if( !$Tag )
		{
			$Posts = [];
			$tagName = ucfirst( str_replace( '-', ' ', $tagSlug ) );
		}
		else
		{
			$status = $this->_ShowDrafts ? null : Post::STATUS_PUBLISHED;
			$Posts = $this->_PostRepository->getByTag( $Tag->getId(), $status );
			$tagName = $Tag->getName();
		}

		$Categories = $this->_CategoryRepository->all();
		$Tags = $this->_TagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $Categories,
				'Tags'        => $Tags,
				'Posts'       => $Posts,
				'Title'       => "Articles tagged with $tagName | " . $this->getName(),
				'Tag'         => $tagName
			],
			'index'
		);
	}

	/**
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function category( array $Parameters, ?Request $Request ): string
	{
		$categorySlug = $Parameters['category'];
		$Category = $this->_CategoryRepository->findBySlug( $categorySlug );

		if( !$Category )
		{
			$Posts = [];
			$categoryName = ucfirst( str_replace( '-', ' ', $categorySlug ) );
		}
		else
		{
			$status = $this->_ShowDrafts ? null : Post::STATUS_PUBLISHED;
			$Posts = $this->_PostRepository->getByCategory( $Category->getId(), $status );
			$categoryName = $Category->getName();
		}

		$Categories = $this->_CategoryRepository->all();
		$Tags = $this->_TagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $Categories,
				'Tags'        => $Tags,
				'Posts'       => $Posts,
				'Title'       => "Articles in category $categoryName | " . $this->getName(),
				'Category'    => $categoryName
			],
			'index'
		);
	}

	/**
	 * Generate RSS feed
	 *
	 * @param array $Parameters
	 * @param Request|null $Request
	 * @return string
	 */
	public function feed( array $Parameters, ?Request $Request ): string
	{
		$Posts = $this->_PostRepository->getPublished( 20 );

		// Build RSS XML
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
		$xml .= '<channel>' . "\n";
		$xml .= '<title>' . htmlspecialchars( $this->getName() ) . '</title>' . "\n";
		$xml .= '<link>' . htmlspecialchars( $this->getUrl() ) . '</link>' . "\n";
		$xml .= '<description>' . htmlspecialchars( $this->getDescription() ) . '</description>' . "\n";
		$xml .= '<atom:link href="' . htmlspecialchars( $this->getRssUrl() ) . '" rel="self" type="application/rss+xml" />' . "\n";

		foreach( $Posts as $Post )
		{
			$xml .= '<item>' . "\n";
			$xml .= '<title>' . htmlspecialchars( $Post->getTitle() ) . '</title>' . "\n";
			$xml .= '<link>' . htmlspecialchars( $this->getUrl() . '/blog/article/' . $Post->getSlug() ) . '</link>' . "\n";
			$xml .= '<description>' . htmlspecialchars( $Post->getExcerpt() ?: substr( strip_tags( $Post->getBody() ), 0, 200 ) ) . '</description>' . "\n";

			if( $Post->getPublishedAt() )
			{
				$xml .= '<pubDate>' . $Post->getPublishedAt()->format( 'r' ) . '</pubDate>' . "\n";
			}

			$xml .= '<guid>' . htmlspecialchars( $this->getUrl() . '/blog/article/' . $Post->getSlug() ) . '</guid>' . "\n";

			// Add categories
			foreach( $Post->getCategories() as $Category )
			{
				$xml .= '<category>' . htmlspecialchars( $Category->getName() ) . '</category>' . "\n";
			}

			$xml .= '</item>' . "\n";
		}

		$xml .= '</channel>' . "\n";
		$xml .= '</rss>';

		// Set content type
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		echo $xml;
		exit;
	}

	/**
	 * Get database configuration from settings
	 */
	private function getDatabaseConfig( SettingManager $Settings ): ?array
	{
		try
		{
			$settingNames = $Settings->getSectionSettingNames( 'database' );

			if( empty( $settingNames ) )
			{
				return null;
			}

			$config = [];
			foreach( $settingNames as $name )
			{
				$value = $Settings->get( 'database', $name );
				if( $value !== null )
				{
					// Convert string values to appropriate types
					if( $name === 'port' )
					{
						$config[$name] = (int)$value;
					}
					else
					{
						$config[$name] = $value;
					}
				}
			}

			return $config;
		}
		catch( \Exception $e )
		{
			return null;
		}
	}
}
