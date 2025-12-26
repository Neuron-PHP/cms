<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Creator;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Services\User\Deleter;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * User management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Users extends Content
{
	private DatabaseUserRepository $_repository;
	private Creator $_userCreator;
	private Updater $_userUpdater;
	private Deleter $_userDeleter;

	/**
	 * @param Application|null $app
	 * @param DatabaseUserRepository|null $repository
	 * @param Creator|null $userCreator
	 * @param Updater|null $userUpdater
	 * @param Deleter|null $userDeleter
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabaseUserRepository $repository = null,
		?Creator $userCreator = null,
		?Updater $userUpdater = null,
		?Deleter $userDeleter = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $repository === null )
		{
			// Get settings and initialize repository
			$settings = Registry::getInstance()->get( 'Settings' );
			$repository = new DatabaseUserRepository( $settings );

			// Initialize services
			$hasher = new PasswordHasher();
			$userCreator = new Creator( $repository, $hasher );
			$userUpdater = new Updater( $repository, $hasher );
			$userDeleter = new Deleter( $repository );
		}

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
	public function index( Request $request ): string
	{
		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$sessionManager = $this->getSessionManager();

		return $this->view()
			->title( 'Users' )
			->description( 'User Management' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'users' => $this->_repository->all(),
				'Success' => $sessionManager->getFlash( 'success' ),
				'Error' => $sessionManager->getFlash( 'error' )
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
		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Create User' )
			->description( 'Create New User' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'roles', [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER] )
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
		$role = $request->post( 'role', User::ROLE_SUBSCRIBER );

		// Basic validation
		if( empty( $username ) || empty( $email ) || empty( $password ) )
		{
			$this->redirect( 'admin_users_create', [], ['error', 'All fields are required'] );
		}

		try
		{
			$this->_userCreator->create( $username, $email, $password, $role );
			$this->redirect( 'admin_users', [], ['success', 'User created successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users_create', [], ['error', $e->getMessage()] );
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
			$this->redirect( 'admin_users', [], ['error', 'User not found'] );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		return $this->view()
			->title( 'Edit User' )
			->description( 'Edit User' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'user' => $user,
				'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
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
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$id = (int)$request->getRouteParameter( 'id' );
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->redirect( 'admin_users', [], ['error', 'User not found'] );
		}

		$usernameInput = $request->post( 'username', null );
		$emailInput = $request->post( 'email', null );

		$username = $usernameInput !== null ? trim( (string)$usernameInput ) : $user->getUsername();
		$email = $emailInput !== null ? trim( (string)$emailInput ) : $user->getEmail();

		if( $username === '' || $email === '' )
		{
			$this->redirect( 'admin_users_edit', ['id' => $id], ['error', 'Username and email are required'] );
		}

		$role = $request->post( 'role', $user->getRole() );
		$password = $request->post( 'password', null );

		try
		{
			$this->_userUpdater->update( $user, $username, $email, $role, $password );
			$this->redirect( 'admin_users', [], ['success', 'User updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users_edit', ['id' => $id], ['error', $e->getMessage()] );
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
		if( !auth() )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$id = (int)$request->getRouteParameter( 'id' );

		// Prevent self-deletion
		if( user_id() === $id )
		{
			$this->redirect( 'admin_users', [], ['error', 'Cannot delete your own account'] );
		}

		try
		{
			$this->_userDeleter->delete( $id );
			$this->redirect( 'admin_users', [], ['success', 'User deleted successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_users', [], ['error', $e->getMessage()] );
		}
	}
}
