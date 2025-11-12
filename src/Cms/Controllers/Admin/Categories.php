<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Services\Category\Creator;
use Neuron\Cms\Services\Category\Updater;
use Neuron\Cms\Services\Category\Deleter;
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
class Categories extends Content
{
	private DatabaseCategoryRepository $_categoryRepository;
	private Creator $_categoryCreator;
	private Updater $_categoryUpdater;
	private Deleter $_categoryDeleter;

	/**
	 * @param Application|null $app
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get settings and initialize repository
		$settings = Registry::getInstance()->get( 'Settings' );
		$this->_categoryRepository = new DatabaseCategoryRepository( $settings );

		// Initialize services
		$this->_categoryCreator = new Creator( $this->_categoryRepository );
		$this->_categoryUpdater = new Updater( $this->_categoryRepository );
		$this->_categoryDeleter = new Deleter( $this->_categoryRepository );
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
	 * Show create category form
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
	 * @return never
	 * @throws \Exception
	 */
	public function store( array $parameters, ?Request $request ): never
	{
		try
		{
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );
			$description = $request->post( 'description' );

			$this->_categoryCreator->create( $name, $slug, $description );
			$this->redirect( 'admin_categories', [], ['success', 'Category created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories_create', [], ['error', $e->getMessage()] );
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
	 * @return never
	 * @throws \Exception
	 */
	public function update( array $parameters, ?Request $request ): never
	{
		$categoryId = (int)$parameters['id'];
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_categories', [], ['error', 'Category not found'] );
		}

		try
		{
			$name = $request->post( 'name' );
			$slug = $request->post( 'slug' );
			$description = $request->post( 'description' );

			$this->_categoryUpdater->update( $category, $name, $slug, $description );
			$this->redirect( 'admin_categories', [], ['success', 'Category updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories_edit', ['id' => $categoryId], ['error', $e->getMessage()] );
		}
	}

	/**
	 * Delete category
	 * @param array $parameters
	 * @param Request|null $request
	 * @return never
	 * @throws \Exception
	 */
	public function destroy( array $parameters, ?Request $request ): never
	{
		$categoryId = (int)$parameters['id'];

		try
		{
			$this->_categoryDeleter->delete( $categoryId );
			$this->redirect( 'admin_categories', [], ['success', 'Category deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories', [], ['error', $e->getMessage()] );
		}
	}
}
