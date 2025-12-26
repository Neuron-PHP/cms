<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Services\EventCategory\Creator;
use Neuron\Cms\Services\EventCategory\Updater;
use Neuron\Cms\Services\EventCategory\Deleter;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Log\Log;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Admin event category management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class EventCategories extends Content
{
	private DatabaseEventCategoryRepository $_repository;
	private Creator $_creator;
	private Updater $_updater;
	private Deleter $_deleter;

	/**
	 * @param Application|null $app
	 * @param DatabaseEventCategoryRepository|null $repository
	 * @param Creator|null $creator
	 * @param Updater|null $updater
	 * @param Deleter|null $deleter
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabaseEventCategoryRepository $repository = null,
		?Creator $creator = null,
		?Updater $updater = null,
		?Deleter $deleter = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $repository === null )
		{
			$settings = Registry::getInstance()->get( 'Settings' );

			$repository = new DatabaseEventCategoryRepository( $settings );
			$creator = new Creator( $repository );
			$updater = new Updater( $repository );
			$deleter = new Deleter( $repository );
		}

		$this->_repository = $repository;
		$this->_creator = $creator;
		$this->_updater = $updater;
		$this->_deleter = $deleter;
	}

	/**
	 * List all event categories
	 */
	public function index( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Event Categories' )
			->description( 'Manage event categories' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'categories' => $this->_repository->all(),
				'Success' => $sessionManager->getFlash( 'success' ),
				'Error' => $sessionManager->getFlash( 'error' )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create category form
	 */
	public function create( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Create Event Category' )
			->description( 'Create a new event category' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'errors' => $sessionManager->getFlash( 'errors' ) ?: [],
				'old' => $sessionManager->getFlash( 'old' ) ?: []
			])
			->render( 'create', 'admin' );
	}

	/**
	 * Store new category
	 */
	public function store( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Validate CSRF token before any state changes
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event category creation by user " . user_id() );
			$this->redirect( 'admin_event_categories_create', [], ['error', 'Invalid security token. Please try again.'] );
		}

		$name = $request->post( 'name', '' );
		$slug = $request->post( 'slug', '' );
		$color = $request->post( 'color', '#3b82f6' );
		$description = $request->post( 'description', '' );

		try
		{
			$this->_creator->create(
				$name,
				$slug ?: null,
				$color,
				$description ?: null
			);

			$this->redirect( 'admin_event_categories', [], ['success', 'Event category created successfully'] );
		}
		catch( \Exception $e )
		{
			// Store old input and errors in session for display
			$sessionManager = $this->getSessionManager();
			$sessionManager->setFlash( 'errors', [ $e->getMessage() ] );
			$sessionManager->setFlash( 'old', [
				'name' => $name,
				'slug' => $slug,
				'color' => $color,
				'description' => $description
			]);

			$this->redirect( 'admin_event_categories_create' );
		}
	}

	/**
	 * Show edit category form
	 */
	public function edit( Request $request ): string
	{
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_repository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_event_categories', [], ['error', 'Category not found'] );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Edit Event Category' )
			->description( 'Edit event category' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'category', $category )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update category
	 */
	public function update( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_repository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_event_categories', [], ['error', 'Category not found'] );
		}

		// Validate CSRF token before any state changes
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event category update: Category {$categoryId}, user " . user_id() );
			$this->redirect( 'admin_event_categories_edit', ['id' => $categoryId], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$name = $request->post( 'name', '' );
			$slug = $request->post( 'slug', '' );
			$color = $request->post( 'color', '#3b82f6' );
			$description = $request->post( 'description', '' );

			$this->_updater->update(
				$category,
				$name,
				$slug,
				$color,
				$description ?: null
			);

			$this->redirect( 'admin_event_categories', [], ['success', 'Event category updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_event_categories_edit', ['id' => $categoryId], ['error', 'Failed to update category: ' . $e->getMessage()] );
		}
	}

	/**
	 * Delete category
	 */
	public function destroy( Request $request ): never
	{
		$user = auth();

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$categoryId = (int)$request->getRouteParameter( 'id' );
		$category = $this->_repository->findById( $categoryId );

		if( !$category )
		{
			$this->redirect( 'admin_event_categories', [], ['error', 'Category not found'] );
		}

		// Validate CSRF token before any state changes
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		$submittedToken = $request->post( 'csrf_token', '' );

		if( !$csrfToken->validate( $submittedToken ) )
		{
			Log::warning( "CSRF validation failed for event category deletion: Category {$categoryId}, user " . user_id() );
			$this->redirect( 'admin_event_categories', [], ['error', 'Invalid security token. Please try again.'] );
		}

		try
		{
			$this->_deleter->delete( $category );
			$this->redirect( 'admin_event_categories', [], ['success', 'Event category deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_event_categories', [], ['error', 'Failed to delete category: ' . $e->getMessage()] );
		}
	}
}
