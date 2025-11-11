<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Services\Post\Creator;
use Neuron\Cms\Services\Post\Updater;
use Neuron\Cms\Services\Post\Deleter;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
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

	private DatabasePostRepository $_postRepository;
	private DatabaseCategoryRepository $_categoryRepository;
	private DatabaseTagRepository $_tagRepository;
	private Creator $_postCreator;
	private Updater $_postUpdater;
	private Deleter $_postDeleter;

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

		// Initialize services
		$tagResolver = new TagResolver(
			$this->_tagRepository,
			new \Neuron\Cms\Services\Tag\Creator( $this->_tagRepository )
		);

		$this->_postCreator = new Creator(
			$this->_postRepository,
			$this->_categoryRepository,
			$tagResolver
		);

		$this->_postUpdater = new Updater(
			$this->_postRepository,
			$this->_categoryRepository,
			$tagResolver
		);

		$this->_postDeleter = new Deleter( $this->_postRepository );
	}

	/**
	 * List all posts
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function index( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$sessionManager = new SessionManager();
		$sessionManager->start();
		$csrfManager = new CsrfTokenManager( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		// Get all posts or filter by author if not admin
		if( $user->isAdmin() || $user->isEditor() )
		{
			$posts = $this->_postRepository->all();
		}
		else
		{
			$posts = $this->_postRepository->getByAuthor( $user->getUsername() );
		}

		$viewData = [
			'Title' => 'Posts | ' . $this->getName(),
			'Description' => 'Manage blog posts',
			'User' => $user,
			'posts' => $posts,
			'Success' => $sessionManager->getFlash( 'success' ),
			'Error' => $sessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'index' );

		return $view->render( $viewData );
	}

	/**
	 * Show create post form
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function create( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$sessionManager = new SessionManager();
		$sessionManager->start();
		$csrfManager = new CsrfTokenManager( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Create Post | ' . $this->getName(),
			'Description' => 'Create a new blog post',
			'User' => $user,
			'categories' => $this->_categoryRepository->all()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'create' );

		return $view->render( $viewData );
	}

	/**
	 * Store new post
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function store( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );
		$sessionManager = new SessionManager();
		$sessionManager->start();

		if( !$user )
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

			// Create post using service
			$this->_postCreator->create(
				$title,
				$content,
				$user->getId(),
				$status,
				$slug ?: null,
				$excerpt ?: null,
				$featuredImage ?: null,
				$categoryIds,
				$tagNames
			);

			$sessionManager->flash( 'success', 'Post created successfully' );
			header( 'Location: /admin/posts' );
			exit;
		}
		catch( \Exception $e )
		{
			$sessionManager->flash( 'error', 'Failed to create post: ' . $e->getMessage() );
			header( 'Location: /admin/posts/create' );
			exit;
		}
	}

	/**
	 * Show edit post form
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function edit( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$parameters['id'];
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$sessionManager = new SessionManager();
			$sessionManager->start();
			$sessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $post->getAuthor() !== $user->getUsername() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this post' );
		}

		// Generate CSRF token
		$sessionManager = new SessionManager();
		$sessionManager->start();
		$csrfManager = new CsrfTokenManager( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Edit Post | ' . $this->getName(),
			'Description' => 'Edit blog post',
			'User' => $user,
			'post' => $post,
			'categories' => $this->_categoryRepository->all()
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Posts' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $view->render( $viewData );
	}

	/**
	 * Update post
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function update( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );
		$sessionManager = new SessionManager();
		$sessionManager->start();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$parameters['id'];
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$sessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $post->getAuthor() !== $user->getUsername() )
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

			// Update post using service
			$this->_postUpdater->update(
				$post,
				$title,
				$content,
				$status,
				$slug ?: null,
				$excerpt ?: null,
				$featuredImage ?: null,
				$categoryIds,
				$tagNames
			);

			$sessionManager->flash( 'success', 'Post updated successfully' );
			header( 'Location: /admin/posts' );
			exit;
		}
		catch( \Exception $e )
		{
			$sessionManager->flash( 'error', 'Failed to update post: ' . $e->getMessage() );
			header( 'Location: /admin/posts/' . $postId . '/edit' );
			exit;
		}
	}

	/**
	 * Delete post
	 * @param array $parameters
	 * @return string
	 */
	public function destroy( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );
		$sessionManager = new SessionManager();
		$sessionManager->start();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$parameters['id'];
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$sessionManager->flash( 'error', 'Post not found' );
			header( 'Location: /admin/posts' );
			exit;
		}

		// Check permissions
		if( !$user->isAdmin() && !$user->isEditor() && $post->getAuthor() !== $user->getUsername() )
		{
			$sessionManager->flash( 'error', 'Unauthorized to delete this post' );
			header( 'Location: /admin/posts' );
			exit;
		}

		try
		{
			$this->_postDeleter->delete( $post );
			$sessionManager->flash( 'success', 'Post deleted successfully' );
		}
		catch( \Exception $e )
		{
			$sessionManager->flash( 'error', 'Failed to delete post: ' . $e->getMessage() );
		}

		header( 'Location: /admin/posts' );
		exit;
	}
}
