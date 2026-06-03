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
use Neuron\Cms\Services\Revision\IRevisionService;
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
	private ?IRevisionService $_revisions;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IPostRepository $postRepository
	 * @param ICategoryRepository $categoryRepository
	 * @param ITagRepository $tagRepository
	 * @param IPostCreator $postCreator
	 * @param IPostUpdater $postUpdater
	 * @param IPostDeleter $postDeleter
	 * @param IRevisionService|null $revisions
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IPostRepository $postRepository,
		ICategoryRepository $categoryRepository,
		ITagRepository $tagRepository,
		IPostCreator $postCreator,
		IPostUpdater $postUpdater,
		IPostDeleter $postDeleter,
		?IRevisionService $revisions = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_postRepository = $postRepository;
		$this->_categoryRepository = $categoryRepository;
		$this->_tagRepository = $tagRepository;
		$this->_postCreator = $postCreator;
		$this->_postUpdater = $postUpdater;
		$this->_postDeleter = $postDeleter;
		$this->_revisions = $revisions;
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
				FlashMessageType::SUCCESS->viewKey() => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey() => $sessionManager->getFlash( FlashMessageType::ERROR->value )
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
			$post = $this->_postCreator->create( $dto, $categoryIds, $tagNames );

			if( $post instanceof Post )
			{
				$this->_revisions?->recordPost( $post, \Neuron\Cms\Models\Revision::ACTION_CREATED );
			}

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
			$post = $this->_postUpdater->update( $dto, $categoryIds, $tagNames );

			if( $post instanceof Post )
			{
				$this->_revisions?->recordPost( $post, \Neuron\Cms\Models\Revision::ACTION_UPDATED );
			}

			$this->redirect( 'admin_posts', [], [FlashMessageType::SUCCESS->value, 'Post updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_edit', ['id' => $postId], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * List revision history for a post
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/posts/:id/history', name: 'admin_posts_history')]
	public function history( Request $request ): string
	{
		$postId = (int)$request->getRouteParameter( 'id' );
		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
		}

		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Unauthorized to view this post'] );
		}

		$this->initializeCsrfToken();

		$revisions = $this->_revisions ? $this->_revisions->listForPost( $postId ) : [];

		return $this->view()
			->title( 'Post History' )
			->description( 'Revision history' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'contentTitle' => $post->getTitle(),
				'contentId'    => $postId,
				'revisions'    => $revisions,
				'routePrefix'  => 'admin_posts',
				'backRoute'    => 'admin_posts_edit'
			])
			->render( 'history', 'admin' );
	}

	/**
	 * View a single post revision
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/posts/:id/history/:revision', name: 'admin_posts_history_show')]
	public function historyShow( Request $request ): string
	{
		$postId = (int)$request->getRouteParameter( 'id' );
		$revisionId = (int)$request->getRouteParameter( 'revision' );

		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
		}

		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Unauthorized to view this post'] );
		}

		$revision = $this->_revisions?->find( $revisionId );

		if( !$revision || $revision->getContentId() !== $postId || $revision->getContentType() !== \Neuron\Cms\Models\Revision::TYPE_POST )
		{
			$this->redirect( 'admin_posts_history', ['id' => $postId], [FlashMessageType::ERROR->value, 'Revision not found'] );
		}

		$this->initializeCsrfToken();

		$snapshot = $revision->getSnapshotData();
		$renderer = new \Neuron\Cms\Services\Content\EditorJsRenderer();
		$contentHtml = $renderer->render( json_decode( (string)( $snapshot['content_raw'] ?? '{"blocks":[]}' ), true ) ?? ['blocks' => []] );

		return $this->view()
			->title( 'View Revision' )
			->description( 'Revision preview' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'contentTitle' => $post->getTitle(),
				'contentId'    => $postId,
				'revision'     => $revision,
				'snapshot'     => $snapshot,
				'contentHtml'  => $contentHtml,
				'routePrefix'  => 'admin_posts',
				'backRoute'    => 'admin_posts_edit'
			])
			->render( 'history-show', 'admin' );
	}

	/**
	 * Restore a post to a previous revision
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[PostRoute('/posts/:id/history/:revision/restore', name: 'admin_posts_history_restore', filters: ['csrf'])]
	public function historyRestore( Request $request ): never
	{
		$postId = (int)$request->getRouteParameter( 'id' );
		$revisionId = (int)$request->getRouteParameter( 'revision' );

		$post = $this->_postRepository->findById( $postId );

		if( !$post )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Post not found'] );
		}

		if( !is_admin() && !is_editor() && $post->getAuthorId() !== user_id() )
		{
			$this->redirect( 'admin_posts', [], [FlashMessageType::ERROR->value, 'Unauthorized to edit this post'] );
		}

		$revision = $this->_revisions?->find( $revisionId );

		if( !$revision || $revision->getContentId() !== $postId || $revision->getContentType() !== \Neuron\Cms\Models\Revision::TYPE_POST )
		{
			$this->redirect( 'admin_posts_history', ['id' => $postId], [FlashMessageType::ERROR->value, 'Revision not found'] );
		}

		try
		{
			$snapshot = $revision->getSnapshotData();

			$post->setTitle( $snapshot['title'] ?? $post->getTitle() );
			$post->setSlug( $snapshot['slug'] ?? $post->getSlug() );
			$post->setContent( (string)( $snapshot['content_raw'] ?? $post->getContentRaw() ) );
			$post->setExcerpt( $snapshot['excerpt'] ?? null );
			$post->setFeaturedImage( $snapshot['featured_image'] ?? null );
			$post->setStatus( $snapshot['status'] ?? $post->getStatus() );

			$this->_postRepository->update( $post );
			$this->_revisions?->recordPost( $post, \Neuron\Cms\Models\Revision::ACTION_RESTORED );

			$this->redirect( 'admin_posts_edit', ['id' => $postId], [FlashMessageType::SUCCESS->value, 'Post restored from revision'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_posts_history', ['id' => $postId], [FlashMessageType::ERROR->value, 'Failed to restore revision: ' . $e->getMessage()] );
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
