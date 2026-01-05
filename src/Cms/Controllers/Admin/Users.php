<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Cms\Enums\UserRole;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * User management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Users extends Content
{
	private IUserRepository $_repository;
	private IUserCreator $_userCreator;
	private IUserUpdater $_userUpdater;
	private IUserDeleter $_userDeleter;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IUserRepository $repository
	 * @param IUserCreator $userCreator
	 * @param IUserUpdater $userUpdater
	 * @param IUserDeleter $userDeleter
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IUserRepository $repository,
		IUserCreator $userCreator,
		IUserUpdater $userUpdater,
		IUserDeleter $userDeleter
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_userCreator = $userCreator;
		$this->_userUpdater = $userUpdater;
		$this->_userDeleter = $userDeleter;
	}

	/**
	 * List all users
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/users', name: 'admin_users')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$sessionManager = $this->getSessionManager();

		return $this->view()
			->title( 'Users' )
			->description( 'User Management' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'users' => $this->_repository->all(),
				FlashMessageType::SUCCESS->value => $sessionManager->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $sessionManager->getFlash( FlashMessageType::ERROR->value )
			])
			->render( 'index', 'admin' );
	}

	/**
	 * Show create user form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/users/create', name: 'admin_users_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Create User' )
			->description( 'Create New User' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'roles', [UserRole::ADMIN->value, UserRole::EDITOR->value, UserRole::AUTHOR->value, UserRole::SUBSCRIBER->value] )
			->render( 'create', 'admin' );
	}

	/**
	 * Store new user
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Post('/users', name: 'admin_users_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		// Create DTO from YAML configuration
		$dto = $this->createDto( 'users/create-user-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_users_create', $dto->getErrors() );
		}

		try
		{
			// Pass DTO to service
			$this->_userCreator->create( $dto );
			$this->redirect( 'admin_users', [], [FlashMessageType::SUCCESS->value, 'User created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users_create', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Show edit user form
	 *
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/users/:id/edit', name: 'admin_users_edit')]
	public function edit( Request $request ): string
	{
		$id = (int)$request->getRouteParameter( 'id' );
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->redirect( 'admin_users', [], [FlashMessageType::ERROR->value, 'User not found'] );
		}

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit User' )
			->description( 'Edit User' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'user' => $user,
				'roles' => [UserRole::ADMIN->value, UserRole::EDITOR->value, UserRole::AUTHOR->value, UserRole::SUBSCRIBER->value]
			])
			->render( 'edit', 'admin' );
	}

	/**
	 * Update user
	 *
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Put('/users/:id', name: 'admin_users_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$id = (int)$request->getRouteParameter( 'id' );

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'users/update-user-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from route parameter
		$dto->id = $id;

		// Validate DTO
		if( !$dto->validate() )
		{
			$this->validationError( 'admin_users_edit', $dto->getErrors(), ['id' => $id] );
		}

		try
		{
			// Pass DTO to service
			$this->_userUpdater->update( $dto );
			$this->redirect( 'admin_users', [], [FlashMessageType::SUCCESS->value, 'User updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users_edit', ['id' => $id], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}

	/**
	 * Delete user
	 *
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Delete('/users/:id', name: 'admin_users_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int)$request->getRouteParameter( 'id' );

		// Prevent self-deletion
		if( user_id() === $id )
		{
			$this->redirect( 'admin_users', [], [FlashMessageType::ERROR->value, 'Cannot delete your own account'] );
		}

		try
		{
			$this->_userDeleter->delete( $id );
			$this->redirect( 'admin_users', [], [FlashMessageType::SUCCESS->value, 'User deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}
}
