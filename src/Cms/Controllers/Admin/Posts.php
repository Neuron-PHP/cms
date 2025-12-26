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
	 * @param DatabasePostRepository|null $postRepository
	 * @param DatabaseCategoryRepository|null $categoryRepository
	 * @param DatabaseTagRepository|null $tagRepository
	 * @param Creator|null $postCreator
	 * @param Updater|null $postUpdater
	 * @param Deleter|null $postDeleter
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabasePostRepository $postRepository = null,
		?DatabaseCategoryRepository $categoryRepository = null,
		?DatabaseTagRepository $tagRepository = null,
		?Creator $postCreator = null,
		?Updater $postUpdater = null,
		?Deleter $postDeleter = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $postRepository === null )
		{
			// Get settings for repositories
			$settings = Registry::getInstance()->get( 'Settings' );

			// Initialize repositories
			$postRepository = new DatabasePostRepository( $settings );
			$categoryRepository = new DatabaseCategoryRepository( $settings );
			$tagRepository = new DatabaseTagRepository( $settings );

			// Initialize services
			$tagResolver = new TagResolver(
				$tagRepository,
				new \Neuron\Cms\Services\Tag\Creator( $tagRepository )
			);

			$postCreator = new Creator(
				$postRepository,
				$categoryRepository,
				$tagResolver
			);

			$postUpdater = new Updater(
				$postRepository,
				$categoryRepository,
				$tagResolver
			);

			$postDeleter = new Deleter( $postRepository );
		}

		$this->_postRepository = $postRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_tagRepository = $tagRepository;
		$this->_postCreator = $postCreator;
		$this->_postUpdater = $postUpdater;
		$this->_postDeleter = $postDeleter;
	}

	/**
	 * List all posts
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		// Get all posts or filter by author if not admin
		if( is_admin() || is_editor() )
		{
			$posts = $this->_postRepository->all();
		}
		else
		{
			$posts = $this->_postRepository->getByAuthor( user_id() );
		}

		$sessionManager = $this->getSessionManager();
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
		$this->initializeCsrfToken();

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

		$this->initializeCsrfToken();

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
