<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Services\Category\Creator;
use Neuron\Cms\Services\Category\Updater;
use Neuron\Cms\Services\Category\Deleter;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
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
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		\Neuron\Patterns\Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Categories | Admin' )
			->description( 'Manage blog categories' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'CategoriesWithCount', $this->_categoryRepository->allWithPostCount() )
			->render( 'index', 'categories' );
	}

	/**
	 * Show create category form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		\Neuron\Patterns\Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Create Category | Admin' )
			->description( 'Create a new blog category' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'create', 'categories' );
	}

	/**
	 * Store new category
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function store( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			\Neuron\Log\Log::warning( 'CSRF validation failed for category creation by user ' . user_id() );
			$this->redirect( 'admin_categories_create', [], ['error', 'Invalid security token. Please try again.'] );
		}

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
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		// Generate CSRF token
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		\Neuron\Patterns\Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Edit Category | Admin' )
			->description( 'Edit blog category' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'Category', $category )
			->render( 'edit', 'categories' );
	}

	/**
	 * Update category
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_categories', [], ['error', 'Category not found'] );
		}

		// Validate CSRF token before any state changes or processing
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			\Neuron\Log\Log::warning( "CSRF validation failed for category update: Category {$categoryId}, user " . user_id() );
			$this->redirect( 'admin_categories_edit', ['id' => $categoryId], ['error', 'Invalid security token. Please try again.'] );
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
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function destroy( Request $request ): never
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );

		// Validate CSRF token before any state changes
		$csrfToken = new \Neuron\Cms\Services\Auth\CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			\Neuron\Log\Log::warning( "CSRF validation failed for category deletion: Category {$categoryId}, user " . user_id() );
			$this->redirect( 'admin_categories', [], ['error', 'Invalid security token. Please try again.'] );
		}

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
