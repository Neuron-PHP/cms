<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin tag management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Tags extends Content
{
	private ITagRepository $_tagRepository;
	private SlugGenerator $_slugGenerator;

	/**
	 * @param Application|null $app
	 * @param ITagRepository|null $tagRepository
	 * @param SlugGenerator|null $slugGenerator
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?ITagRepository $tagRepository = null,
		?SlugGenerator $slugGenerator = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_tagRepository = $tagRepository ?? $app?->getContainer()?->get( ITagRepository::class );
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
	}

	/**
	 * List all tags
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/tags', name: 'admin_tags')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Tags | Admin' )
			->description( 'Manage blog tags' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'tags', $this->_tagRepository->allWithPostCount() )
			->render( 'index', 'admin' );
	}

	/**
	 * Show create tag form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/tags/create', name: 'admin_tags_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create Tag | Admin' )
			->description( 'Create a new blog tag' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'create', 'admin' );
	}

	/**
	 * Store new tag
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Post('/tags', name: 'admin_tags_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'tags/create-tag-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_tags_create', $dto->getErrors() );
		}

		try
		{
			// Extract values from DTO
			$name = $dto->name;
			$slug = $dto->slug ?? '';

			// Create tag
			$tag = new Tag();
			$tag->setName( $name );
			$tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_tagRepository->create( $tag );

			$this->redirect( 'admin_tags', [], [FlashMessageType::SUCCESS->value, 'Tag created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags_create', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Show edit tag form

	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/tags/:id/edit', name: 'admin_tags_edit')]
	public function edit( Request $request ): string
	{
		$tagId = (int)$request->getRouteParameter( 'id' );
		$tag = $this->_tagRepository->findById( $tagId );

		if( !$tag )
		{
			throw new \RuntimeException( 'Tag not found' );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Tag | Admin' )
			->description( 'Edit blog tag' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'tag', $tag )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update tag
	 *
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	#[Put('/tags/:id', name: 'admin_tags_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$tagId = (int)$request->getRouteParameter( 'id' );

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'tags/update-tag-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $tagId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_tags_edit', $dto->getErrors(), ['id' => $tagId] );
		}

		$tag = $this->_tagRepository->findById( $tagId );

		if( !$tag )
		{
			$this->redirect( 'admin_tags', [], [FlashMessageType::ERROR->value, 'Tag not found'] );
		}

		try
		{
			// Extract values from DTO
			$name = $dto->name;
			$slug = $dto->slug ?? '';

			// Update tag
			$tag->setName( $name );
			$tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_tagRepository->update( $tag );

			$this->redirect( 'admin_tags', [], [FlashMessageType::SUCCESS->value, 'Tag updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags_edit', ['id' => $tagId], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Delete tag

	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Delete('/tags/:id', name: 'admin_tags_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$tagId = (int)$request->getRouteParameter( 'id' );

		try
		{
			$this->_tagRepository->delete( $tagId );
			$this->redirect( 'admin_tags', [], [FlashMessageType::SUCCESS->value, 'Tag deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags', [], [FlashMessageType::ERROR->value, 'Failed to delete tag: ' . $e->getMessage()] );
		}
	}

	/**
	 * Generate slug from name
	 *
	 * For names with only non-ASCII characters (e.g., "你好", "مرحبا"),
	 * generates a fallback slug using uniqid().
	 *
	 * @param string $name
	 * @return string
	 * @throws \Exception
	 */
	private function generateSlug( string $name ): string
	{
		return $this->_slugGenerator->generate( $name, 'tag' );
	}
}
