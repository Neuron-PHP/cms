<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Mvc\Views\Html;
use Neuron\Patterns\Registry;

/**
 * User management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class UserController extends Content
{

	private DatabaseUserRepository $_repository;
	private PasswordHasher $_hasher;
	private SessionManager $_sessionManager;

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
		$this->_hasher = new PasswordHasher();
		$this->_sessionManager = new SessionManager();
		$this->_sessionManager->start();
	}

	/**
	 * List all users
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function index( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$csrfManager = new CsrfTokenManager( $this->_sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$users = $this->_repository->all();

		$viewData = [
			'Title' => 'Users | ' . $this->getName(),
			'Description' => 'User Management',
			'User' => $user,
			'users' => $users,
			'Success' => $this->_sessionManager->getFlash( 'success' ),
			'Error' => $this->_sessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'index' );

		return $view->render( $viewData );
	}

	/**
	 * Show create user form
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function create( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$csrfManager = new CsrfTokenManager( $this->_sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Create User | ' . $this->getName(),
			'Description' => 'Create New User',
			'User' => $user,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'create' );

		return $view->render( $viewData );
	}

	/**
	 * Store new user
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function store( array $parameters ): string
	{
		$username = $_POST['username'] ?? '';
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		$role = $_POST['role'] ?? User::ROLE_SUBSCRIBER;

		// Validate
		if( empty( $username ) || empty( $email ) || empty( $password ) )
		{
			$this->_sessionManager->flash( 'error', 'All fields are required' );
			header( 'Location: /admin/users/create' );
			exit;
		}

		if( !$this->_hasher->meetsRequirements( $password ) )
		{
			$this->_sessionManager->flash( 'error', 'Password does not meet requirements' );
			header( 'Location: /admin/users/create' );
			exit;
		}

		// Create user
		$newUser = new User();
		$newUser->setUsername( $username );
		$newUser->setEmail( $email );
		$newUser->setPasswordHash( $this->_hasher->hash( $password ) );
		$newUser->setRole( $role );
		$newUser->setStatus( User::STATUS_ACTIVE );
		$newUser->setEmailVerified( true );

		try
		{
			$this->_repository->create( $newUser );
			$this->_sessionManager->flash( 'success', 'User created successfully' );
		}
		catch( \Exception $e )
		{
			$this->_sessionManager->flash( 'error', 'Failed to create user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}

	/**
	 * Show edit user form
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function edit( array $parameters ): string
	{
		$id = (int)$parameters['id'];
		$currentUser = Registry::getInstance()->get( 'Auth.User' );
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->_sessionManager->flash( 'error', 'User not found' );
			header( 'Location: /admin/users' );
			exit;
		}

		// Generate CSRF token
		$csrfManager = new CsrfTokenManager( $this->_sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Edit User | ' . $this->getName(),
			'Description' => 'Edit User',
			'User' => $currentUser,
			'user' => $user,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $view->render( $viewData );
	}

	/**
	 * Update user
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function update( array $parameters ): string
	{
		$id = (int)$parameters['id'];
		$user = $this->_repository->findById( $id );

		if( !$user )
		{
			$this->_sessionManager->flash( 'error', 'User not found' );
			header( 'Location: /admin/users' );
			exit;
		}

		$email = $_POST['email'] ?? '';
		$role = $_POST['role'] ?? $user->getRole();
		$password = $_POST['password'] ?? '';

		$user->setEmail( $email );
		$user->setRole( $role );

		// Update password if provided
		if( !empty( $password ) )
		{
			if( !$this->_hasher->meetsRequirements( $password ) )
			{
				$this->_sessionManager->flash( 'error', 'Password does not meet requirements' );
				header( 'Location: /admin/users/' . $id . '/edit' );
				exit;
			}

			$user->setPasswordHash( $this->_hasher->hash( $password ) );
		}

		try
		{
			$this->_repository->update( $user );
			$this->_sessionManager->flash( 'success', 'User updated successfully' );
		}
		catch( \Exception $e )
		{
			$this->_sessionManager->flash( 'error', 'Failed to update user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}

	/**
	 * Delete user
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function destroy( array $parameters ): string
	{
		$id = (int)$parameters['id'];
		$currentUser = Registry::getInstance()->get( 'Auth.User' );

		// Prevent self-deletion
		if( $currentUser && $currentUser->getId() === $id )
		{
			$this->_sessionManager->flash( 'error', 'Cannot delete your own account' );
			header( 'Location: /admin/users' );
			exit;
		}

		try
		{
			$this->_repository->delete( $id );
			$this->_sessionManager->flash( 'success', 'User deleted successfully' );
		}
		catch( \Exception $e )
		{
			$this->_sessionManager->flash( 'error', 'Failed to delete user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}
}
