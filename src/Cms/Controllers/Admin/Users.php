<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Cms\Enums\UserRole;

/**
 * User management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Users extends Content
{
	private IUserRepository $_repository;
	private IUserCreator $_userCreator;
	private IUserUpdater $_userUpdater;
	private IUserDeleter $_userDeleter;

	/**
	 * @param Application|null $app
	 * @param IUserRepository|null $repository
	 * @param IUserCreator|null $userCreator
	 * @param IUserUpdater|null $userUpdater
	 * @param IUserDeleter|null $userDeleter
	 */
	public function __construct(
		?Application $app = null,
		?IUserRepository $repository = null,
		?IUserCreator $userCreator = null,
		?IUserUpdater $userUpdater = null,
		?IUserDeleter $userDeleter = null
	)
	{
		parent::__construct( $app );

		// Use dependency injection when available (container provides dependencies)
		// Otherwise resolve from container (fallback for compatibility)
		$this->_repository = $repository ?? $app?->getContainer()?->get( IUserRepository::class );
		$this->_userCreator = $userCreator ?? $app?->getContainer()?->get( IUserCreator::class );
		$this->_userUpdater = $userUpdater ?? $app?->getContainer()?->get( IUserUpdater::class );
		$this->_userDeleter = $userDeleter ?? $app?->getContainer()?->get( IUserDeleter::class );
	}

	/**
	 * List all users
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
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
	public function store( Request $request ): never
	{
		$username = $request->post( 'username','' );
		$email = $request->post( 'email', '' );
		$password = $request->post( 'password', '' );
		$role = $request->post( 'role', UserRole::SUBSCRIBER->value );

		// Basic validation
		if( empty( $username ) || empty( $email ) || empty( $password ) )
		{
			$this->redirect( 'admin_users_create', [], [FlashMessageType::ERROR->value, 'All fields are required'] );
		}

		try
		{
			$this->_userCreator->create( $username, $email, $password, $role );
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
	public function update( Request $request ): never
	{
		$id = (int)$request->getRouteParameter( 'id' );
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->redirect( 'admin_users', [], [FlashMessageType::ERROR->value, 'User not found'] );
		}

		$usernameInput = $request->post( 'username', null );
		$emailInput = $request->post( 'email', null );

		$username = $usernameInput !== null ? trim( (string)$usernameInput ) : $user->getUsername();
		$email = $emailInput !== null ? trim( (string)$emailInput ) : $user->getEmail();

		if( $username === '' || $email === '' )
		{
			$this->redirect( 'admin_users_edit', ['id' => $id], [FlashMessageType::ERROR->value, 'Username and email are required'] );
		}

		$role = $request->post( 'role', $user->getRole() );
		$password = $request->post( 'password', null );

		try
		{
			$this->_userUpdater->update( $user, $username, $email, $role, $password );
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
