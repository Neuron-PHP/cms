<?php

namespace Neuron\Cms\Controllers\Admin;

use DateTimeImmutable;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;

/**
 * Admin post management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class PostController extends Content
{
	private DatabasePostRepository $_PostRepository;
	private DatabaseCategoryRepository $_CategoryRepository;
	private DatabaseTagRepository $_TagRepository;

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
	}

	/**
	 * List all posts
	 */
	public function index( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$SessionManager = new SessionManager();
		$SessionManager->start();
		$CsrfManager = new CsrfTokenManager( $SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		// Get all posts or filter by author if not admin
		if( $User->isAdmin() || $User->isEditor() )
		{
			$posts = $this->_PostRepository->all();
		}
		else
		{
			$posts = $this->_PostRepository->getByAuthor( $User->getUsername() );
		}

		$ViewData = [
			'Title' => 'Posts | ' . $this->getName(),
			'Description' => 'Manage blog posts',
			'User' => $User,
			'posts' => $posts,
			'Success' => $SessionManager->getFlash( 'success' ),
			'Error' => $SessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'index' );

		return $View->render( $ViewData );
	}

	/**
	 * Show create post form
	 */
	public function create( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$SessionManager = new SessionManager();
		$SessionManager->start();
		$CsrfManager = new CsrfTokenManager( $SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Create Post | ' . $this->getName(),
			'Description' => 'Create a new blog post',
			'User' => $User,
			'categories' => $this->_CategoryRepository->all()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'create' );

		return $View->render( $ViewData );
	}

	/**
	 * Store new post
	 */
	public function store( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );
		$SessionManager = new SessionManager();
		$SessionManager->start();

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get form data
			$title = $_POST['title'] ?? '';
			$slug = $_POST['slug'] ?? '';
			$content = $_POST['content'] ?? '';
			$excerpt = $_POST['excerpt'] ?? '';
			$featuredImage = $_POST['featured_image'] ?? '';
			$status = $_POST['status'] ?? Post::STATUS_DRAFT;
			$categoryIds = $_POST['categories'] ?? [];
			$tagNames = $_POST['tags'] ?? '';

			// Create post
			$Post = new Post();
			$Post->setTitle( $title );
			$Post->setSlug( $slug ?: $this->generateSlug( $title ) );
			$Post->setContent( $content );
			$Post->setExcerpt( $excerpt );
			$Post->setFeaturedImage( $featuredImage );
			$Post->setAuthor( $User->getUsername() );
			$Post->setStatus( $status );
			$Post->setCreatedAt( new DateTimeImmutable() );

			// Set published date if status is published
			if( $status === Post::STATUS_PUBLISHED )
			{
				$Post->setPublishedAt( new DateTimeImmutable() );
			}

			// Load categories
			$categories = [];
			foreach( $categoryIds as $categoryId )
			{
				$category = $this->_CategoryRepository->findById( (int)$categoryId );
				if( $category )
				{
					$categories[] = $category;
				}
			}
			$Post->setCategories( $categories );

			// Parse and create/load tags
			$tags = [];
			if( !empty( $tagNames ) )
			{
				$tagArray = array_map( 'trim', explode( ',', $tagNames ) );
				foreach( $tagArray as $tagName )
				{
					if( empty( $tagName ) ) continue;

					$tag = $this->_TagRepository->findByName( $tagName );
					if( !$tag )
					{
						$tag = new Tag();
						$tag->setName( $tagName );
						$tag->setSlug( $this->generateSlug( $tagName ) );
						$this->_TagRepository->create( $tag );
					}
					$tags[] = $tag;
				}
			}
			$Post->setTags( $tags );

			// Save post
			$this->_PostRepository->create( $Post );

			$SessionManager->flash( 'success', 'Post created successfully' );
			header( 'Location: /admin/posts' );
			exit;
		}
		catch( \Exception $e )
		{
			$SessionManager->flash( 'error', 'Failed to create post: ' . $e->getMessage() );
			header( 'Location: /admin/posts/create' );
			exit;
		}
	}

	/**
	 * Show edit post form
	 */
	public function edit( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$Parameters['id'];
		$post = $this->_PostRepository->findById( $postId );

		if( !$post )
		{
			$SessionManager = new SessionManager();
			$SessionManager->start();
			$SessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$User->isAdmin() && !$User->isEditor() && $post->getAuthor() !== $User->getUsername() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this post' );
		}

		// Generate CSRF token
		$SessionManager = new SessionManager();
		$SessionManager->start();
		$CsrfManager = new CsrfTokenManager( $SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Edit Post | ' . $this->getName(),
			'Description' => 'Edit blog post',
			'User' => $User,
			'post' => $post,
			'categories' => $this->_CategoryRepository->all()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $View->render( $ViewData );
	}

	/**
	 * Update post
	 */
	public function update( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );
		$SessionManager = new SessionManager();
		$SessionManager->start();

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$Parameters['id'];
		$Post = $this->_PostRepository->findById( $postId );

		if( !$Post )
		{
			$SessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$User->isAdmin() && !$User->isEditor() && $Post->getAuthor() !== $User->getUsername() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this post' );
		}

		try
		{
			// Get form data
			$title = $_POST['title'] ?? '';
			$slug = $_POST['slug'] ?? '';
			$content = $_POST['content'] ?? '';
			$excerpt = $_POST['excerpt'] ?? '';
			$featuredImage = $_POST['featured_image'] ?? '';
			$status = $_POST['status'] ?? Post::STATUS_DRAFT;
			$categoryIds = $_POST['categories'] ?? [];
			$tagNames = $_POST['tags'] ?? '';

			// Update post
			$Post->setTitle( $title );
			$Post->setSlug( $slug ?: $this->generateSlug( $title ) );
			$Post->setContent( $content );
			$Post->setExcerpt( $excerpt );
			$Post->setFeaturedImage( $featuredImage );
			$Post->setStatus( $status );

			// Set published date if status changed to published
			if( $status === Post::STATUS_PUBLISHED && !$Post->getPublishedAt() )
			{
				$Post->setPublishedAt( new DateTimeImmutable() );
			}

			// Load categories
			$categories = [];
			foreach( $categoryIds as $categoryId )
			{
				$category = $this->_CategoryRepository->findById( (int)$categoryId );
				if( $category )
				{
					$categories[] = $category;
				}
			}
			$Post->setCategories( $categories );

			// Parse and create/load tags
			$tags = [];
			if( !empty( $tagNames ) )
			{
				$tagArray = array_map( 'trim', explode( ',', $tagNames ) );
				foreach( $tagArray as $tagName )
				{
					if( empty( $tagName ) ) continue;

					$tag = $this->_TagRepository->findByName( $tagName );
					if( !$tag )
					{
						$tag = new Tag();
						$tag->setName( $tagName );
						$tag->setSlug( $this->generateSlug( $tagName ) );
						$this->_TagRepository->create( $tag );
					}
					$tags[] = $tag;
				}
			}
			$Post->setTags( $tags );

			// Save post
			$this->_PostRepository->update( $Post );

			$SessionManager->flash( 'success', 'Post updated successfully' );
			header( 'Location: /admin/posts' );
			exit;
		}
		catch( \Exception $e )
		{
			$SessionManager->flash( 'error', 'Failed to update post: ' . $e->getMessage() );
			header( 'Location: /admin/posts/' . $postId . '/edit' );
			exit;
		}
	}

	/**
	 * Delete post
	 */
	public function destroy( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );
		$SessionManager = new SessionManager();
		$SessionManager->start();

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$Parameters['id'];
		$Post = $this->_PostRepository->findById( $postId );

		if( !$Post )
		{
			$SessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$User->isAdmin() && !$User->isEditor() && $Post->getAuthor() !== $User->getUsername() )
		{
			$SessionManager->flash( 'error', 'Unauthorized to delete this post' );
			header( 'Location: /admin/posts' );
			exit;
		}

		try
		{
			$this->_PostRepository->delete( $postId );
			$SessionManager->flash( 'success', 'Post deleted successfully' );
		}
		catch( \Exception $e )
		{
			$SessionManager->flash( 'error', 'Failed to delete post: ' . $e->getMessage() );
		}

		header( 'Location: /admin/posts' );
		exit;
	}

	/**
	 * Generate slug from title
	 */
	private function generateSlug( string $title ): string
	{
		$slug = strtolower( trim( $title ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		return trim( $slug, '-' );
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
