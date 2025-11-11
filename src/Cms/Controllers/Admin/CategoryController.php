<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Core\Exceptions\NotFound;
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

	private DatabaseCategoryRepository $_categoryRepository;

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
		$this->_categoryRepository = new DatabaseCategoryRepository( $settings );
	}

	/**
	 * List categories
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

		$categoriesWithCount = $this->_categoryRepository->allWithPostCount();

		$viewData = [
			'Title' => 'Categories | Admin | ' . $this->getName(),
			'Description' => 'Manage blog categories',
			'User' => $user,
			'CategoriesWithCount' => $categoriesWithCount
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'categories'
		);
	}

	/**
	 * List categories
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
			'Title' => 'Create Category | Admin | ' . $this->getName(),
			'Description' => 'Create a new blog category',
			'User' => $user
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'create',
			'categories'
		);
	}

	/**
	 * Store new category
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws NotFound
	 */
	public function store( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			// Get form data
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );
			$description = $request->post( 'description' );

			// Create category
			$category = new Category();
			$category->setName( $name );
			$category->setSlug( $slug ?: $this->generateSlug( $name ) );
			$category->setDescription( $description );

			// Save category
			$this->_categoryRepository->create( $category );

			// Redirect to category list
			header( 'Location: /admin/categories' );
			exit;
		}
		catch( \Exception $e )
		{
			$viewData = [
				'Title' => 'Create Category | Admin | ' . $this->getName(),
				'Description' => 'Create a new blog category',
				'User' => $user,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$viewData,
				'create',
				'categories'
			);
		}
	}

	/**
	 * Show edit category form
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

		$categoryId = (int)$parameters['id'];
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		$viewData = [
			'Title' => 'Edit Category | Admin | ' . $this->getName(),
			'Description' => 'Edit blog category',
			'User' => $user,
			'Category' => $category
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'categories'
		);
	}

	/**
	 * Update category
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	public function update( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$parameters['id'];
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		try
		{
			// Get form data
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );
			$description = $request->post( 'description' );

			// Update category
			$category->setName( $name );
			$category->setSlug( $slug ?: $this->generateSlug( $name ) );
			$category->setDescription( $description );

			// Save category
			$this->_categoryRepository->update( $category );

			// Redirect to category list
			header( 'Location: /admin/categories' );
			exit;
		}
		catch( \Exception $e )
		{
			$viewData = [
				'Title' => 'Edit Category | Admin | ' . $this->getName(),
				'Description' => 'Edit blog category',
				'User' => $user,
				'Category' => $category,
				'Error' => $e->getMessage()
			];

			return $this->renderHtml(
				HttpResponseStatus::BAD_REQUEST,
				$viewData,
				'edit',
				'categories'
			);
		}
	}

	/**
	 * Delete category
	 * @param array $parameters
	 * @param Request|null $request
	 * @return string
	 * @throws \Exception
	 */
	public function destroy( array $parameters, ?Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$parameters['id'];
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		try
		{
			$this->_categoryRepository->delete( $categoryId );

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
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		return trim( $slug, '-' );
	}

}
