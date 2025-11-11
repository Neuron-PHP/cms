<?php
namespace Neuron\Cms\Controllers;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Data\Filter\Get;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;

class Blog extends Content
{

	private DatabasePostRepository $_postRepository;
	private DatabaseCategoryRepository $_categoryRepository;
	private DatabaseTagRepository $_tagRepository;
	private bool $_showDrafts = false;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get settings for repositories
		$settings = Registry::getInstance()->get( 'Settings' );

		// Initialize repositories
		$this->_postRepository = new DatabasePostRepository( $settings );
		$this->_categoryRepository = new DatabaseCategoryRepository( $settings );
		$this->_tagRepository = new DatabaseTagRepository( $settings );

		// Check for drafts parameter
		$get = new Get();
		$this->_showDrafts = $get->filterScalar( 'drafts' ) ? true : false;
	}

	/**
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function index( array $parameters, ?Request $request ): string
	{
		// Get published posts (or all if drafts mode)
		if( $this->_showDrafts )
		{
			$posts = $this->_postRepository->all();
		}
		else
		{
			$posts = $this->_postRepository->getPublished();
		}

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Posts'       => $posts,
				'Categories' => $categories,
				'Tags'        => $tags,
				'Title'       => $this->getName() . ' | ' . $this->getTitle(),
				'Name'        => $this->getName(),
				'Description' => $this->getDescription(),
			],
			'index'
		);
	}

	/**
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function show( array $parameters, ?Request $request ): string
	{
		$slug = $parameters['slug'] ?? '';
		$post = $this->_postRepository->findBySlug( $slug );

		if( !$post || ( !$post->isPublished() && !$this->_showDrafts ) )
		{
			$post = new Post();
			$post->setTitle( 'Article Not Found' );
			$post->setBody( 'The requested article does not exist.' );
			$post->setSlug( $slug );
		}
		else
		{
			// Increment view count for published posts
			if( $post->isPublished() )
			{
				$this->_postRepository->incrementViewCount( $post->getId() );
			}
		}

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $categories,
				'Tags'        => $tags,
				'Post'        => $post,
				'Title'       => $post->getTitle() . ' | ' . $this->getName()
			],
			'show'
		);
	}

	/**
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function author( array $parameters, ?Request $request ): string
	{
		$authorName = $parameters['author'] ?? '';

		// Note: This would need a user lookup by username
		// For now, we'll just show an empty list
		$posts = [];

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $categories,
				'Tags'        => $tags,
				'Posts'       => $posts,
				'Title'       => "Articles by $authorName | " . $this->getName(),
				'Author'      => $authorName
			],
			'index'
		);
	}


	/**
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function tag( array $parameters, ?Request $request ): string
	{
		$tagSlug = $parameters['tag'] ?? '';
		$tag = $this->_tagRepository->findBySlug( $tagSlug );

		if( !$tag )
		{
			$posts = [];
			$tagName = ucfirst( str_replace( '-', ' ', $tagSlug ) );
		}
		else
		{
			$status = $this->_showDrafts ? null : Post::STATUS_PUBLISHED;
			$posts = $this->_postRepository->getByTag( $tag->getId(), $status );
			$tagName = $tag->getName();
		}

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $categories,
				'Tags'        => $tags,
				'Posts'       => $posts,
				'Title'       => "Articles tagged with $tagName | " . $this->getName(),
				'Tag'         => $tagName
			],
			'index'
		);
	}

	/**
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Neuron\Core\Exceptions\NotFound
	 */
	public function category( array $parameters, ?Request $request ): string
	{
		$categorySlug = $parameters['category'] ?? '';
		$category = $this->_categoryRepository->findBySlug( $categorySlug );

		if( !$category )
		{
			$posts = [];
			$categoryName = ucfirst( str_replace( '-', ' ', $categorySlug ) );
		}
		else
		{
			$status = $this->_showDrafts ? null : Post::STATUS_PUBLISHED;
			$posts = $this->_postRepository->getByCategory( $category->getId(), $status );
			$categoryName = $category->getName();
		}

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $categories,
				'Tags'        => $tags,
				'Posts'       => $posts,
				'Title'       => "Articles in category $categoryName | " . $this->getName(),
				'Category'    => $categoryName
			],
			'index'
		);
	}

	/**
	 * Generate RSS feed
	 *
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 */
	public function feed( array $parameters, ?Request $request ): string
	{
		$posts = $this->_postRepository->getPublished( 20 );

		// Build RSS XML
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
		$xml .= '<channel>' . "\n";
		$xml .= '<title>' . htmlspecialchars( $this->getName() ) . '</title>' . "\n";
		$xml .= '<link>' . htmlspecialchars( $this->getUrl() ) . '</link>' . "\n";
		$xml .= '<description>' . htmlspecialchars( $this->getDescription() ) . '</description>' . "\n";
		$xml .= '<atom:link href="' . htmlspecialchars( $this->getRssUrl() ) . '" rel="self" type="application/rss+xml" />' . "\n";

		foreach( $posts as $post )
		{
			$xml .= '<item>' . "\n";
			$xml .= '<title>' . htmlspecialchars( $post->getTitle() ) . '</title>' . "\n";
			$xml .= '<link>' . htmlspecialchars( $this->getUrl() . '/blog/article/' . $post->getSlug() ) . '</link>' . "\n";
			$xml .= '<description>' . htmlspecialchars( $post->getExcerpt() ?: substr( strip_tags( $post->getBody() ), 0, 200 ) ) . '</description>' . "\n";

			if( $post->getPublishedAt() )
			{
				$xml .= '<pubDate>' . $post->getPublishedAt()->format( 'r' ) . '</pubDate>' . "\n";
			}

			$xml .= '<guid>' . htmlspecialchars( $this->getUrl() . '/blog/article/' . $post->getSlug() ) . '</guid>' . "\n";

			// Add categories
			foreach( $post->getCategories() as $category )
			{
				$xml .= '<category>' . htmlspecialchars( $category->getName() ) . '</category>' . "\n";
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
}
