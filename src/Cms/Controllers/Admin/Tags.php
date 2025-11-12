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
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get settings for repositories
		$settings = Registry::getInstance()->get( 'Settings' );

		// Initialize repository
		$this->_tagRepository = new DatabaseTagRepository( $settings );
	}

	/**
	 * List all tags
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagsWithCount = $this->_tagRepository->allWithPostCount();

		$viewData = [
			'Title' => 'Tags | Admin | ' . $this->getName(),
			'Description' => 'Manage blog tags',
			'User' => $user,
			'TagsWithCount' => $tagsWithCount
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'tags'
		);
	}

	/**
	 * Show create tag form
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$viewData = [
			'Title' => 'Create Tag | Admin | ' . $this->getName(),
			'Description' => 'Create a new blog tag',
			'User' => $user
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'create',
			'tags'
		);
	}

	/**
	 * Store new tag
	 * @param array $parameters
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	public function store( array $parameters, ?Request $request ): never
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
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagId = (int)$parameters['id'];
		$tag = $this->_tagRepository->findById( $tagId );

		if( !$tag )
		{
			throw new \RuntimeException( 'Tag not found' );
		}

		$viewData = [
			'Title' => 'Edit Tag | Admin | ' . $this->getName(),
			'Description' => 'Edit blog tag',
			'User' => $user,
			'Tag' => $tag
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'tags'
		);
	}

	/**
	 * Update tag
	 * @param array $parameters
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( array $parameters, ?Request $request ): never
	{
		$tagId = (int)$parameters['id'];
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
	 * @param array $parameters
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	public function destroy( array $parameters, ?Request $request ): never
	{
		$tagId = (int)$parameters['id'];

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
	 * @param string $name
	 * @return string
	 * @throws \Exception
	 */
	private function generateSlug( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		return trim( $slug, '-' );
	}
}
