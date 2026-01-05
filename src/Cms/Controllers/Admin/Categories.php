<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\ICategoryCreator;
use Neuron\Cms\Services\Category\ICategoryUpdater;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin category management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Categories extends Content
{
	private ICategoryRepository $_categoryRepository;
	private ICategoryCreator $_categoryCreator;
	private ICategoryUpdater $_categoryUpdater;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param ICategoryRepository $categoryRepository
	 * @param ICategoryCreator $categoryCreator
	 * @param ICategoryUpdater $categoryUpdater
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		ICategoryRepository $categoryRepository,
		ICategoryCreator $categoryCreator,
		ICategoryUpdater $categoryUpdater
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_categoryRepository = $categoryRepository;
		$this->_categoryCreator = $categoryCreator;
		$this->_categoryUpdater = $categoryUpdater;
	}

	/**
	 * List categories
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/categories', name: 'admin_categories')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Categories | Admin' )
			->description( 'Manage blog categories' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'CategoriesWithCount', $this->_categoryRepository->allWithPostCount() )
			->render( 'index', 'admin' );
	}

	/**
	 * Show create category form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/categories/create', name: 'admin_categories_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create Category | Admin' )
			->description( 'Create a new blog category' )
			->withCurrentUser()
			->withCsrfToken()
			->render( 'create', 'admin' );
	}

	/**
	 * Store new category
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Post('/categories', name: 'admin_categories_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'categories/create-category-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_categories_create', $dto->getErrors() );
		}

		try
		{
			$this->_categoryCreator->create( $dto );
			$this->redirect( 'admin_categories', [], [FlashMessageType::SUCCESS->value, 'Category created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories_create', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Show edit category form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/categories/:id/edit', name: 'admin_categories_edit')]
	public function edit( Request $request ): string
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \RuntimeException( 'Category not found' );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Category | Admin' )
			->description( 'Edit blog category' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'Category', $category )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update category
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Put('/categories/:id', name: 'admin_categories_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'categories/update-category-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $categoryId;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_categories_edit', $dto->getErrors(), ['id' => $categoryId] );
		}

		try
		{
			$this->_categoryUpdater->update( $dto );
			$this->redirect( 'admin_categories', [], [FlashMessageType::SUCCESS->value, 'Category updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories_edit', ['id' => $categoryId], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Delete category
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Delete('/categories/:id', name: 'admin_categories_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$categoryId = (int)$request->getRouteParameter( 'id' );

		try
		{
			$this->_categoryRepository->delete( $categoryId );
			$this->redirect( 'admin_categories', [], [FlashMessageType::SUCCESS->value, 'Category deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_categories', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}
}
