<?php
namespace Neuron\Cms\Controllers;

use JetBrains\PhpStorm\NoReturn;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

#[RouteGroup(prefix: '/blog')]
class Blog extends Content
{
	private ?IPostRepository $_postRepository = null;
	private ?ICategoryRepository $_categoryRepository = null;
	private ?ITagRepository $_tagRepository = null;
	private ?IUserRepository $_userRepository = null;
	private ?EditorJsRenderer $_renderer = null;

	/**
	 * @param Application|null $app
	 * @param IPostRepository|null $postRepository
	 * @param ICategoryRepository|null $categoryRepository
	 * @param ITagRepository|null $tagRepository
	 * @param IUserRepository|null $userRepository
	 * @param EditorJsRenderer|null $renderer
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IPostRepository $postRepository = null,
		?ICategoryRepository $categoryRepository = null,
		?ITagRepository $tagRepository = null,
		?IUserRepository $userRepository = null,
		?EditorJsRenderer $renderer = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_postRepository = $postRepository ?? $app?->getContainer()?->get( IPostRepository::class );
		$this->_categoryRepository = $categoryRepository ?? $app?->getContainer()?->get( ICategoryRepository::class );
		$this->_tagRepository = $tagRepository ?? $app?->getContainer()?->get( ITagRepository::class );
		$this->_userRepository = $userRepository ?? $app?->getContainer()?->get( IUserRepository::class );
		$this->_renderer = $renderer ?? $app?->getContainer()?->get( EditorJsRenderer::class );
	}

	/**
	 * Blog homepage - list of published posts
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/', name: 'blog')]
	public function index( Request $request ): string
	{
		$posts = $this->_postRepository->getPublished();

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Posts'       => $posts,
				'Categories'  => $categories,
				'Tags'        => $tags,
				'Title'       => $this->getName() . ' | ' . $this->getTitle(),
				'Name'        => $this->getName(),
				'Description' => $this->getDescription(),
			],
			'index'
		);
	}

	/**
	 * Blog article detail view
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/post/:slug', name: 'blog_post')]
	public function show( Request $request ): string
	{
		$slug = $request->getRouteParameter( 'slug', '' );
		$post = $this->_postRepository->findBySlug( $slug );

		if( !$post )
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

		// Render content from Editor.js JSON
		$content = $post->getContent();
		$renderedContent = $this->_renderer?->render( $content ) ?? (is_array($content) ? json_encode($content) : $content);

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Categories' => $categories,
				'Tags'        => $tags,
				'Post'        => $post,
				'renderedContent' => $renderedContent,
				'Title'       => $post->getTitle() . ' | ' . $this->getName()
			],
			'show'
		);
	}

	/**
	 * Blog posts by author
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/author/:username', name: 'blog_author')]
	public function author( Request $request ): string
	{
		$authorName = $request->getRouteParameter( 'author', '' );

		// Look up user by username
		$user = $this->_userRepository->findByUsername( $authorName );

		// Get posts by this author (only published posts for public view)
		$posts = [];
		if( $user )
		{
			$posts = $this->_postRepository->getByAuthor( $user->getId(), ContentStatus::PUBLISHED->value );
		}

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
	 * Blog posts by tag
	 *
	 * @param Request|null $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/tag/:slug', name: 'blog_tag')]
	public function tag( Request $request ): string
	{
		$tagSlug = $request->getRouteParameter( 'tag', '' );
		$tag = $this->_tagRepository->findBySlug( $tagSlug );

		if( !$tag )
		{
			$posts = [];
			$tagName = ucfirst( str_replace( '-', ' ', $tagSlug ) );
		}
		else
		{
			$status = ContentStatus::PUBLISHED->value;
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
	 * Blog posts by category
	 *
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/category/:slug', name: 'blog_category')]
	public function category( Request $request ): string
	{
		$categorySlug = $request->getRouteParameter( 'category', '' );
		$category = $this->_categoryRepository->findBySlug( $categorySlug );

		if( !$category )
		{
			$posts = [];
			$categoryName = ucfirst( str_replace( '-', ' ', $categorySlug ) );
		}
		else
		{
			$status = ContentStatus::PUBLISHED->value;
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
	 * @param Request $request
	 * @return string
	 */
	#[NoReturn]
	#[Get('/rss', name: 'rss_feed')]
	public function feed( Request $request ): string
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
