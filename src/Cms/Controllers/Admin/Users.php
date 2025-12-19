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
	 * @throws \Exception
	 */
	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get settings and initialize repository
		$settings = Registry::getInstance()->get( 'Settings' );
		$this->_repository = new DatabaseUserRepository( $settings );

		// Initialize services
		$hasher = new PasswordHasher();
		$this->_userCreator = new Creator( $this->_repository, $hasher );
		$this->_userUpdater = new Updater( $this->_repository, $hasher );
		$this->_userDeleter = new Deleter( $this->_repository );
	}

	/**
	 * List all users
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function index( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$users = $this->_repository->all();

		$viewData = [
			'Title' => 'Users | ' . $this->getName(),
			'Description' => 'User Management',
			'User' => $user,
			'users' => $users,
			'Success' => $this->getSessionManager()->getFlash( 'success' ),
			'Error' => $this->getSessionManager()->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'index',
			'admin'
		);
	}

	/**
	 * Show create user form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function create( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Create User | ' . $this->getName(),
			'Description' => 'Create New User',
			'User' => $user,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'create',
			'admin'
		);
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
		$currentUser = Registry::getInstance()->get( 'Auth.User' );
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->redirect( 'admin_users', [], ['error', 'User not found'] );
		}

		// Generate CSRF token
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		$viewData = [
			'Title' => 'Edit User | ' . $this->getName(),
			'Description' => 'Edit User',
			'User' => $currentUser,
			'user' => $user,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'admin'
		);
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
		$id = (int)$request->getRouteParameter( 'id' );
		$currentUser = Registry::getInstance()->get( 'Auth.User' );

		// Prevent self-deletion
		if( $currentUser && $currentUser->getId() === $id )
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
