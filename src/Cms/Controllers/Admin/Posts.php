<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Post\IPostCreator;
use Neuron\Cms\Services\Post\IPostUpdater;
use Neuron\Cms\Services\Post\IPostDeleter;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post as PostRoute;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin post management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Posts extends Content
{
	private IPostRepository $_postRepository;
	private ICategoryRepository $_categoryRepository;
	private ITagRepository $_tagRepository;
	private IPostCreator $_postCreator;
	private IPostUpdater $_postUpdater;
	private IPostDeleter $_postDeleter;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPostRepository|null $postRepository
	 * @param ICategoryRepository|null $categoryRepository
	 * @param ITagRepository|null $tagRepository
	 * @param IPostCreator|null $postCreator
	 * @param IPostUpdater|null $postUpdater
	 * @param IPostDeleter|null $postDeleter
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IPostRepository $postRepository = null,
		?ICategoryRepository $categoryRepository = null,
		?ITagRepository $tagRepository = null,
		?IPostCreator $postCreator = null,
		?IPostUpdater $postUpdater = null,
		?IPostDeleter $postDeleter = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		if( $postRepository === null )
		{
			throw new \InvalidArgumentException( 'IPostRepository must be injected' );
		}
		$this->_postRepository = $postRepository;

		if( $categoryRepository === null )
		{
			throw new \InvalidArgumentException( 'ICategoryRepository must be injected' );
		}
		$this->_categoryRepository = $categoryRepository;

		if( $tagRepository === null )
		{
			throw new \InvalidArgumentException( 'ITagRepository must be injected' );
		}
		$this->_tagRepository = $tagRepository;

		if( $postCreator === null )
		{
			throw new \InvalidArgumentException( 'IPostCreator must be injected' );
		}
		$this->_postCreator = $postCreator;

		if( $postUpdater === null )
		{
			throw new \InvalidArgumentException( 'IPostUpdater must be injected' );
		}
		$this->_postUpdater = $postUpdater;

		if( $postDeleter === null )
		{
			throw new \InvalidArgumentException( 'IPostDeleter must be injected' );
		}
		$this->_postDeleter = $postDeleter;
	}

	/**
	 * List all posts
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/posts', name: 'admin_posts')]
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
	#[Get('/posts/create', name: 'admin_posts_create')]
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
	#[PostRoute('/posts', name: 'admin_posts_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'posts/create-post-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set author from current user
		$dto->author_id = user_id();

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_posts_create', $dto->getErrors() );
		}

		try
		{
			// Get categories and tags from request (not in DTO due to array validation limitations)
			$categoryIds = $request->post( 'categories', [] );
			$tagNames = $request->post( 'tags', '' );

			// Pass DTO to service
			$this->_postCreator->create( $dto, $categoryIds, $tagNames );
			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_create', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Show edit post form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/posts/:id/edit', name: 'admin_posts_edit')]
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
	#[Put('/posts/:id', name: 'admin_posts_update', filters: ['csrf'])]
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

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'posts/update-post-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $postId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_posts_edit', $dto->getErrors(), ['id' => $postId] );
		}

		try
		{
			// Get categories and tags from request (not in DTO due to array validation limitations)
			$categoryIds = $request->post( 'categories', [] );
			$tagNames = $request->post( 'tags', '' );

			// Pass DTO to service
			$this->_postUpdater->update( $dto, $categoryIds, $tagNames );
			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_edit', ['id' => $postId], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Delete post
	 * @param Request $request
	 * @return never
	 */
	#[Delete('/posts/:id', name: 'admin_posts_destroy', filters: ['csrf'])]
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
