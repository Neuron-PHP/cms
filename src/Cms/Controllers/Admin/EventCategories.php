<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Services\EventCategory\Creator;
use Neuron\Cms\Services\EventCategory\Updater;
use Neuron\Cms\Services\EventCategory\Deleter;
use Neuron\Cms\Services\Auth\CsrfToken;
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
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		$settings = Registry::getInstance()->get( 'Settings' );

		$this->_repository = new DatabaseEventCategoryRepository( $settings );
		$this->_creator = new Creator( $this->_repository );
		$this->_updater = new Updater( $this->_repository );
		$this->_deleter = new Deleter( $this->_repository );
	}

	/**
	 * List all event categories
	 */
	public function index( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$sessionManager = $this->getSessionManager();
		$csrfToken = new CsrfToken( $sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$categories = $this->_repository->all();

		$viewData = [
			'Title' => 'Event Categories | ' . $this->getName(),
			'Description' => 'Manage event categories',
			'User' => $user,
			'categories' => $categories,
			'Success' => $sessionManager->getFlash( 'success' ),
			'Error' => $sessionManager->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'admin'
		);
	}

	/**
	 * Show create category form
	 */
	public function create( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Create Event Category | ' . $this->getName(),
			'Description' => 'Create a new event category',
			'User' => $user
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'create',
			'admin'
		);
	}

	/**
	 * Store new category
	 */
	public function store( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		try
		{
			$name = $request->post( 'name', '' );
			$slug = $request->post( 'slug', '' );
			$color = $request->post( 'color', '#3b82f6' );
			$description = $request->post( 'description', '' );

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
			$this->redirect( 'admin_event_categories_create', [], ['error', 'Failed to create category: ' . $e->getMessage()] );
		}
	}

	/**
	 * Show edit category form
	 */
	public function edit( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

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

		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Edit Event Category | ' . $this->getName(),
			'Description' => 'Edit event category',
			'User' => $user,
			'category' => $category
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'admin'
		);
	}

	/**
	 * Update category
	 */
	public function update( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

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
		$user = Registry::getInstance()->get( 'Auth.User' );

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
