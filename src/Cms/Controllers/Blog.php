<?php
namespace Neuron\Cms\Controllers;

use JetBrains\PhpStorm\NoReturn;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

#[RouteGroup(prefix: '/blog')]
class Blog extends Content
{
	private IPostRepository $_postRepository;
	private ICategoryRepository $_categoryRepository;
	private ITagRepository $_tagRepository;
	private IUserRepository $_userRepository;
	private EditorJsRenderer $_renderer;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPostRepository $postRepository
	 * @param ICategoryRepository $categoryRepository
	 * @param ITagRepository $tagRepository
	 * @param IUserRepository $userRepository
	 * @param EditorJsRenderer $renderer
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPostRepository $postRepository,
		ICategoryRepository $categoryRepository,
		ITagRepository $tagRepository,
		IUserRepository $userRepository,
		EditorJsRenderer $renderer
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_postRepository = $postRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_tagRepository = $tagRepository;
		$this->_userRepository = $userRepository;
		$this->_renderer = $renderer;
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
		$title = $this->getName() . ' | ' . $this->getTitle();

		return $this->renderListing(
			$request,
			$this->paginatePublished( $request ),
			$title,
			$this->getDescription(),
			$this->absoluteRoute( 'blog' ),
			route_path( 'blog' ) ?: '/blog'
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

		$responseStatus = HttpResponseStatus::OK;

		if( !$post || !$post->isPublished() )
		{
			$post = new Post();
			$post->setTitle( 'Article Not Found' );
			$post->setBody( 'The requested article does not exist.' );
			$post->setSlug( $slug );
			$responseStatus = HttpResponseStatus::NOT_FOUND;
		}
		else
		{
			// Increment view count for published posts
			$this->_postRepository->incrementViewCount( $post->getId() );
		}

		$categories = $this->_categoryRepository->all();
		$tags = $this->_tagRepository->all();

		// Render content from Editor.js JSON
		$content = $post->getContent();
		$renderedContent = $this->_renderer?->render( $content ) ?? (is_array($content) ? json_encode($content) : $content);

		// Fallback to plain text body if Editor.js content is empty
		if( empty( trim( $renderedContent ) ) && !empty( $post->getBody() ) )
		{
			$renderedContent = '<p>' . htmlspecialchars( $post->getBody() ) . '</p>';
		}

		$metaTitle = $post->getMetaTitle() ?: $post->getTitle();
		$description = $post->getMetaDescription() ?: ( $post->getExcerpt() ?: $this->getDescription() );

		return $this->renderHtml(
			$responseStatus,
			array_merge(
				$this->seoViewData(
					$metaTitle . ' | ' . $this->getName(),
					(string) $description,
					$this->absoluteRoute( 'blog_post', [ 'slug' => $post->getSlug() ] ),
					$post->getFeaturedImage(),
					'article',
					$post->getMetaKeywords()
				),
				$this->breadcrumbViewData( [
					[ 'label' => 'Blog', 'url' => route_path( 'blog' ) ?: '/blog' ],
					[ 'label' => $post->getTitle() ]
				] ),
				[
					'Categories' => $categories,
					'Tags'        => $tags,
					'Post'        => $post,
					'renderedContent' => $renderedContent,
				]
			),
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
		$authorName = $request->getRouteParameter( 'username', '' );

		// Look up user by username
		$user = $this->_userRepository->findByUsername( $authorName );

		$listing = $user
			? $this->paginateFiltered(
				$request,
				fn( int $limit, int $offset ) => $this->_postRepository->getByAuthor( $user->getId(), ContentStatus::PUBLISHED->value, $limit, $offset ),
				fn() => $this->_postRepository->countByAuthor( $user->getId(), ContentStatus::PUBLISHED->value )
			)
			: $this->emptyListing();

		$title = "Articles by $authorName | " . $this->getName();

		return $this->renderListing(
			$request,
			$listing,
			$title,
			$this->getDescription(),
			$this->absoluteRoute( 'blog_author', [ 'username' => $authorName ] ),
			route_path( 'blog_author', [ 'username' => $authorName ] ),
			[ 'Author' => $authorName ]
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
		$tagSlug = $request->getRouteParameter( 'slug', '' );
		$tag = $this->_tagRepository->findBySlug( $tagSlug );

		if( !$tag )
		{
			$tagName = ucfirst( str_replace( '-', ' ', $tagSlug ) );
			$listing = $this->emptyListing();
		}
		else
		{
			$tagName = $tag->getName();
			$status  = ContentStatus::PUBLISHED->value;
			$listing = $this->paginateFiltered(
				$request,
				fn( int $limit, int $offset ) => $this->_postRepository->getByTag( $tag->getId(), $status, $limit, $offset ),
				fn() => $this->_postRepository->countByTag( $tag->getId(), $status )
			);
		}

		$title = "Articles tagged with $tagName | " . $this->getName();

		return $this->renderListing(
			$request,
			$listing,
			$title,
			$this->getDescription(),
			$this->absoluteRoute( 'blog_tag', [ 'slug' => $tagSlug ] ),
			route_path( 'blog_tag', [ 'slug' => $tagSlug ] ),
			[ 'Tag' => $tagName ]
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
		$categorySlug = $request->getRouteParameter( 'slug', '' );
		$category = $this->_categoryRepository->findBySlug( $categorySlug );

		if( !$category )
		{
			$categoryName = ucfirst( str_replace( '-', ' ', $categorySlug ) );
			$listing = $this->emptyListing();
		}
		else
		{
			$categoryName = $category->getName();
			$status       = ContentStatus::PUBLISHED->value;
			$listing      = $this->paginateFiltered(
				$request,
				fn( int $limit, int $offset ) => $this->_postRepository->getByCategory( $category->getId(), $status, $limit, $offset ),
				fn() => $this->_postRepository->countByCategory( $category->getId(), $status )
			);
		}

		$title = "Articles in category $categoryName | " . $this->getName();

		return $this->renderListing(
			$request,
			$listing,
			$title,
			$this->getDescription(),
			$this->absoluteRoute( 'blog_category', [ 'slug' => $categorySlug ] ),
			route_path( 'blog_category', [ 'slug' => $categorySlug ] ),
			[ 'Category' => $categoryName ]
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
			$xml .= '<link>' . htmlspecialchars( $this->absoluteRoute( 'blog_post', [ 'slug' => $post->getSlug() ] ) ) . '</link>' . "\n";
			$xml .= '<description>' . htmlspecialchars( $post->getExcerpt() ?: substr( strip_tags( $post->getBody() ), 0, 200 ) ) . '</description>' . "\n";

			if( $post->getPublishedAt() )
			{
				$xml .= '<pubDate>' . $post->getPublishedAt()->format( 'r' ) . '</pubDate>' . "\n";
			}

			$xml .= '<guid>' . htmlspecialchars( $this->absoluteRoute( 'blog_post', [ 'slug' => $post->getSlug() ] ) ) . '</guid>' . "\n";

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

	/**
	 * @param array{posts: array, page: int, pages: int, perPage: int, total: int} $listing
	 * @param array<string, mixed> $extra
	 */
	private function renderListing(
		Request $request,
		array $listing,
		string $title,
		string $description,
		string $canonical,
		string $paginationBase,
		array $extra = []
	): string
	{
		return $this->renderHtml(
			HttpResponseStatus::OK,
			array_merge(
				$this->seoViewData( $title, $description, $canonical ),
				$this->breadcrumbViewData( $this->listingBreadcrumbs( $extra ) ),
				[
					'Posts' => $listing['posts'],
					'Categories' => $this->_categoryRepository->all(),
					'Tags' => $this->_tagRepository->all(),
					'Name' => $this->getName(),
					'page' => $listing['page'],
					'pages' => $listing['pages'],
					'perPage' => $listing['perPage'],
					'total' => $listing['total'],
					'PaginationBase' => $paginationBase,
				],
				$extra
			),
			'index'
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<int, array{label: string, url?: ?string}>
	 */
	private function listingBreadcrumbs( array $extra ): array
	{
		$blog = [ 'label' => 'Blog', 'url' => route_path( 'blog' ) ?: '/blog' ];

		if( isset( $extra['Category'] ) )
		{
			return [ $blog, [ 'label' => (string) $extra['Category'] ] ];
		}

		if( isset( $extra['Tag'] ) )
		{
			return [ $blog, [ 'label' => (string) $extra['Tag'] ] ];
		}

		if( isset( $extra['Author'] ) )
		{
			return [ $blog, [ 'label' => (string) $extra['Author'] ] ];
		}

		return [ [ 'label' => 'Blog' ] ];
	}

	/**
	 * @return array{posts: array, page: int, pages: int, perPage: int, total: int}
	 */
	private function paginatePublished( Request $request ): array
	{
		return $this->paginateFiltered(
			$request,
			fn( int $limit, int $offset ) => $this->_postRepository->getPublished( $limit, $offset ),
			fn() => $this->_postRepository->count( ContentStatus::PUBLISHED->value )
		);
	}

	/**
	 * @param callable(int, int): array $fetch
	 * @param callable(): int $count
	 * @return array{posts: array, page: int, pages: int, perPage: int, total: int}
	 */
	private function paginateFiltered( Request $request, callable $fetch, callable $count ): array
	{
		$perPage = max( 1, (int) ( $this->_settings->get( 'blog', 'posts_per_page' ) ?? 10 ) );
		$page    = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );
		$total   = $count();
		$pages   = max( 1, (int) ceil( $total / $perPage ) );
		$page    = min( $page, $pages );

		return [
			'posts' => $fetch( $perPage, ( $page - 1 ) * $perPage ),
			'page' => $page,
			'pages' => $total === 0 ? 1 : $pages,
			'perPage' => $perPage,
			'total' => $total,
		];
	}

	/**
	 * @return array{posts: array, page: int, pages: int, perPage: int, total: int}
	 */
	private function emptyListing(): array
	{
		return [
			'posts' => [],
			'page' => 1,
			'pages' => 1,
			'perPage' => max( 1, (int) ( $this->_settings->get( 'blog', 'posts_per_page' ) ?? 10 ) ),
			'total' => 0,
		];
	}
}
