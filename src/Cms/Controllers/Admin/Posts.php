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
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin post management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Posts extends Content
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
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		// Get all posts or filter by author if not admin
		if( is_admin() || is_editor() )
		{
			$posts = $this->_postRepository->all();
		}
		else
		{
			$posts = $this->_postRepository->getByAuthor( $user->getUsername() );
		}

		return $this->view()
			->title( 'Posts' )
			->description( 'Manage blog posts' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'posts' => $posts,
				'Success' => $sessionManager->getFlash( 'success' ),
				'Error' => $sessionManager->getFlash( 'error' )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create post form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Create Post' )
			->description( 'Create a new blog post' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'categories', $this->_categoryRepository->all() )
			->render( 'create', 'admin' );
	}

	/**
	 * Store new post
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function store( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get form data
			$title = $request->post('title', '' );
			$slug = $request->post( 'slug', '' );
			$content = $request->post('content', '' );
			$excerpt = $request->post( 'excerpt', '' );
			$featuredImage = $request->post('featured_image', '' );
			$status = $request->post( 'status', Post::STATUS_DRAFT );
			$categoryIds = $request->post( 'categories', [] );
			$tagNames = $request->post( 'tags', '' );

			// Create post using service
			$this->_postCreator->create(
				$title,
				$content,
				user_id(),
				$status,
				$slug ?: null,
				$excerpt ?: null,
				$featuredImage ?: null,
				$categoryIds,
				$tagNames
			);

			$this->redirect( 'admin_posts', [], ['success', 'Post created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_create', [], ['error', 'Failed to create post: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit post form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$request->getRouteParameter( 'id' );
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Post not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this post' );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Edit Post' )
			->description( 'Edit blog post' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'post' => $post,
				'categories' => $this->_categoryRepository->all()
			])
			->render( 'edit', 'admin' );
	}

	/**
	 * Update post
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$request->getRouteParameter( 'id' );
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Post not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			throw new \RuntimeException( 'Unauthorized to edit this post' );
		}

		try
		{
			// Get form data
			$title = $request->post( 'title', '' );
			$slug = $request->post('slug', '' );
			$content = $request->post( 'content', '' );
			$excerpt = $request->post( 'excerpt' ,'' );
			$featuredImage = $request->post( 'featured_image', '' );
			$status = $request->post( 'status', Post::STATUS_DRAFT );
			$categoryIds = $request->post( 'categories', [] );
			$tagNames = $request->post( 'tags','' );

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

			$this->redirect( 'admin_posts', [], ['success', 'Post updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_edit', ['id' => $postId], ['error', 'Failed to update post: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete post
	 * @param Request $request
	 * @return never
	 */
	public function destroy( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$postId = (int)$request->getRouteParameter( 'id' );
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Post not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Unauthorized to delete this post'] );
		}

		try
		{
			$this->_postDeleter->delete( $post );
			$this->redirect( 'admin_posts', [], ['success', 'Post deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts', [], ['error', 'Failed to delete post: ' . $e->getMessage()] );
		}
	}
}
