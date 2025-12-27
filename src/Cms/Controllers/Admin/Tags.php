<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin tag management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Tags extends Content
{
	private DatabaseTagRepository $_tagRepository;

	/**
	 * @param Application|null $app
	 * @param DatabaseTagRepository|null $tagRepository
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabaseTagRepository $tagRepository = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $tagRepository === null )
		{
			// Get settings for repositories
			$settings = Registry::getInstance()->get( 'Settings' );

			// Initialize repository
			$tagRepository = new DatabaseTagRepository( $settings );
		}

		$this->_tagRepository = $tagRepository;
	}

	/**
	 * List all tags
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Tags | Admin' )
			->description( 'Manage blog tags' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'tags', $this->_tagRepository->allWithPostCount() )
			->render( 'index', 'tags' );
	}

	/**
	 * Show create tag form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create Tag | Admin' )
			->description( 'Create a new blog tag' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'create', 'tags' );
	}

	/**
	 * Store new tag
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function store( Request $request ): never
	{
		try
		{
			// Get form data
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );

			// Create tag
			$tag = new Tag();
			$tag->setName( $name );
			$tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_tagRepository->create( $tag );

			$this->redirect( 'admin_tags', [], ['success', 'Tag created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags_create', [], ['error', $e->getMessage()] );
		}
	}

	/**
	 * Show edit tag form

	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
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
			->render( 'edit', 'tags' );
	}

	/**
	 * Update tag
	 *
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		$tagId = (int)$request->getRouteParameter( 'id' );
		$tag = $this->_tagRepository->findById( $tagId );

		if( !$tag )
		{
			$this->redirect( 'admin_tags', [], ['error', 'Tag not found'] );
		}

		try
		{
			// Get form data
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );

			// Update tag
			$tag->setName( $name );
			$tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_tagRepository->update( $tag );

			$this->redirect( 'admin_tags', [], ['success', 'Tag updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags_edit', ['id' => $tagId], ['error', $e->getMessage()] );
		}
	}

	/**
	 * Delete tag

	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function destroy( Request $request ): never
	{
		$tagId = (int)$request->getRouteParameter( 'id' );

		try
		{
			$this->_tagRepository->delete( $tagId );
			$this->redirect( 'admin_tags', [], ['success', 'Tag deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_tags', [], ['error', 'Failed to delete tag: ' . $e->getMessage()] );
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
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		// Fallback for names with no ASCII characters
		if( $slug === '' )
		{
			$slug = 'tag-' . uniqid();
		}

		return $slug;
	}
}
