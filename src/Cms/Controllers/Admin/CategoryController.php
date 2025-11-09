<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin category management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class CategoryController extends Content
{
	private DatabaseCategoryRepository $_CategoryRepository;

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
		$this->_CategoryRepository = new DatabaseCategoryRepository( $dbConfig );
	}

	/**
	 * List all categories
	 */
	public function index( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoriesWithCount = $this->_CategoryRepository->allWithPostCount();

		$ViewData = [
			'Title' => 'Categories | Admin | ' . $this->getName(),
			'Description' => 'Manage blog categories',
			'User' => $User,
			'CategoriesWithCount' => $categoriesWithCount
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'index',
			'categories'
		);
	}

	/**
	 * Show create category form
	 */
	public function create( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$ViewData = [
			'Title' => 'Create Category | Admin | ' . $this->getName(),
			'Description' => 'Create a new blog category',
			'User' => $User
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'create',
			'categories'
		);
	}

	/**
	 * Store new category
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
			$description = $Request->post( 'description' );

			// Create category
			$Category = new Category();
			$Category->setName( $name );
			$Category->setSlug( $slug ?: $this->generateSlug( $name ) );
			$Category->setDescription( $description );

			// Save category
			$this->_CategoryRepository->create( $Category );

			// Redirect to category list
			header( 'Location: /admin/categories' );
			exit;
		}
		catch( \Exception $e )
		{
			$ViewData = [
				'Title' => 'Create Category | Admin | ' . $this->getName(),
				'Description' => 'Create a new blog category',
				'User' => $User,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$ViewData,
				'create',
				'categories'
			);
		}
	}

	/**
	 * Show edit category form
	 */
	public function edit( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$Parameters['id'];
		$Category = $this->_CategoryRepository->findById( $categoryId );

		if( !$Category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		$ViewData = [
			'Title' => 'Edit Category | Admin | ' . $this->getName(),
			'Description' => 'Edit blog category',
			'User' => $User,
			'Category' => $Category
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$ViewData,
			'edit',
			'categories'
		);
	}

	/**
	 * Update category
	 */
	public function update( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$Parameters['id'];
		$Category = $this->_CategoryRepository->findById( $categoryId );

		if( !$Category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		try
		{
			// Get form data
			$name = $Request->post( 'name' );
			$slug = $Request->post( 'slug' );
			$description = $Request->post( 'description' );

			// Update category
			$Category->setName( $name );
			$Category->setSlug( $slug ?: $this->generateSlug( $name ) );
			$Category->setDescription( $description );

			// Save category
			$this->_CategoryRepository->update( $Category );

			// Redirect to category list
			header( 'Location: /admin/categories' );
			exit;
		}
		catch( \Exception $e )
		{
			$ViewData = [
				'Title' => 'Edit Category | Admin | ' . $this->getName(),
				'Description' => 'Edit blog category',
				'User' => $User,
				'Category' => $Category,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$ViewData,
				'edit',
				'categories'
			);
		}
	}

	/**
	 * Delete category
	 */
	public function destroy( array $Parameters, ?Request $Request ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$Parameters['id'];
		$Category = $this->_CategoryRepository->findById( $categoryId );

		if( !$Category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		try
		{
			$this->_CategoryRepository->delete( $categoryId );

			// Redirect to category list
			header( 'Location: /admin/categories' );
			exit;
		}
		catch( \Exception $e )
		{
			throw new \RuntimeException( 'Failed to delete category: ' . $e->getMessage() );
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
