<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin tag management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class TagController extends Content
{
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

		// Initialize repository
		$this->_TagRepository = new DatabaseTagRepository( $dbConfig );
	}

	/**
	 * List all tags
	 */
	public function index( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagsWithCount = $this->_TagRepository->allWithPostCount();

		$ViewData = [
			'Title' => 'Tags | Admin | ' . $this->getName(),
			'Description' => 'Manage blog tags',
			'User' => $User,
			'TagsWithCount' => $tagsWithCount
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'index',
			'tags'
		);
	}

	/**
	 * Show create tag form
	 */
	public function create( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$ViewData = [
			'Title' => 'Create Tag | Admin | ' . $this->getName(),
			'Description' => 'Create a new blog tag',
			'User' => $User
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'create',
			'tags'
		);
	}

	/**
	 * Store new tag
	 */
	public function store( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get form data
			$name = $Request->post( 'name' );
			$slug = $Request->post( 'slug' );

			// Create tag
			$Tag = new Tag();
			$Tag->setName( $name );
			$Tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_TagRepository->create( $Tag );

			// Redirect to tag list
			header( 'Location: /admin/tags' );
			exit;
		}
		catch( \Exception $e )
		{
			$ViewData = [
				'Title' => 'Create Tag | Admin | ' . $this->getName(),
				'Description' => 'Create a new blog tag',
				'User' => $User,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$ViewData,
				'create',
				'tags'
			);
		}
	}

	/**
	 * Show edit tag form
	 */
	public function edit( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagId = (int)$Parameters['id'];
		$Tag = $this->_TagRepository->findById( $tagId );

		if( !$Tag )
		{
			throw new \RuntimeException( 'Tag not found' );
		}

		$ViewData = [
			'Title' => 'Edit Tag | Admin | ' . $this->getName(),
			'Description' => 'Edit blog tag',
			'User' => $User,
			'Tag' => $Tag
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'edit',
			'tags'
		);
	}

	/**
	 * Update tag
	 */
	public function update( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagId = (int)$Parameters['id'];
		$Tag = $this->_TagRepository->findById( $tagId );

		if( !$Tag )
		{
			throw new \RuntimeException( 'Tag not found' );
		}

		try
		{
			// Get form data
			$name = $Request->post( 'name' );
			$slug = $Request->post( 'slug' );

			// Update tag
			$Tag->setName( $name );
			$Tag->setSlug( $slug ?: $this->generateSlug( $name ) );

			// Save tag
			$this->_TagRepository->update( $Tag );

			// Redirect to tag list
			header( 'Location: /admin/tags' );
			exit;
		}
		catch( \Exception $e )
		{
			$ViewData = [
				'Title' => 'Edit Tag | Admin | ' . $this->getName(),
				'Description' => 'Edit blog tag',
				'User' => $User,
				'Tag' => $Tag,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$ViewData,
				'edit',
				'tags'
			);
		}
	}

	/**
	 * Delete tag
	 */
	public function destroy( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$tagId = (int)$Parameters['id'];
		$Tag = $this->_TagRepository->findById( $tagId );

		if( !$Tag )
		{
			throw new \RuntimeException( 'Tag not found' );
		}

		try
		{
			$this->_TagRepository->delete( $tagId );

			// Redirect to tag list
			header( 'Location: /admin/tags' );
			exit;
		}
		catch( \Exception $e )
		{
			throw new \RuntimeException( 'Failed to delete tag: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate slug from name
	 */
	private function generateSlug( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
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
