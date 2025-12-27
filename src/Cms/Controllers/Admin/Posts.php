<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
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
use Neuron\Cms\Enums\ContentStatus;

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

		// Get settings once if we need to create any repositories
		$settings = null;
		if( $postRepository === null || $categoryRepository === null || $tagRepository === null )
		{
			$settings = Registry::getInstance()->get( 'Settings' );
		}

		// Individually ensure each repository is initialized
		if( $postRepository === null )
		{
			$postRepository = new DatabasePostRepository( $settings );
		}

		if( $categoryRepository === null )
		{
			$categoryRepository = new DatabaseCategoryRepository( $settings );
		}

		if( $tagRepository === null )
		{
			$tagRepository = new DatabaseTagRepository( $settings );
		}

		// Build downstream services using guaranteed non-null repositories
		// Create TagResolver if needed for Creator/Updater services
		$tagResolver = new TagResolver(
			$tagRepository,
			new \Neuron\Cms\Services\Tag\Creator( $tagRepository )
		);

		if( $postCreator === null )
		{
			$postCreator = new Creator(
				$postRepository,
				$categoryRepository,
				$tagResolver
			);
		}

		if( $postUpdater === null )
		{
			$postUpdater = new Updater(
				$postRepository,
				$categoryRepository,
				$tagResolver
			);
		}

		if( $postDeleter === null )
		{
			$postDeleter = new Deleter( $postRepository );
		}

		// Assign to properties with defensive checks
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
				FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
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
			$status = $request->post( 'status', ContentStatus::DRAFT->value );
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

			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_create', [], [FlashMessageType::ERROR->value, 'Failed to create post: ' . $e->getMessage()] );
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
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
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
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
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
			$status = $request->post( 'status', ContentStatus::DRAFT->value );
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

			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_edit', ['id' => $postId], [FlashMessageType::ERROR->value, 'Failed to update post: ' . $e->getMessage()] );
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
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
		}

		// Check permissions
		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Unauthorized to delete this post'] );
		}

		try
		{
			$this->_postDeleter->delete( $post );
			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Failed to delete post: ' . $e->getMessage()] );
		}
	}
}
