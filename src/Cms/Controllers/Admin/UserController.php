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
	private DatabaseUserRepository $_Repository;
	private PasswordHasher $_Hasher;
	private SessionManager $_SessionManager;

	public function __construct( ?Application $app = null )
	{
		parent::__construct( $app );

		// Get database config and initialize repository
		$Settings = Registry::getInstance()->get( 'Settings' );
		$dbConfig = $this->getDatabaseConfig( $Settings );

		$this->_Repository = new DatabaseUserRepository( $dbConfig );
		$this->_Hasher = new PasswordHasher();
		$this->_SessionManager = new SessionManager();
		$this->_SessionManager->start();
	}

	/**
	 * List all users
	 */
	public function index( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$CsrfManager = new CsrfTokenManager( $this->_SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$users = $this->_Repository->all();

		$ViewData = [
			'Title' => 'Users | ' . $this->getName(),
			'Description' => 'User Management',
			'User' => $User,
			'users' => $users,
			'Success' => $this->_SessionManager->getFlash( 'success' ),
			'Error' => $this->_SessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'index' );

		return $View->render( $ViewData );
	}

	/**
	 * Show create user form
	 */
	public function create( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		// Generate CSRF token
		$CsrfManager = new CsrfTokenManager( $this->_SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Create User | ' . $this->getName(),
			'Description' => 'Create New User',
			'User' => $User,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'create' );

		return $View->render( $ViewData );
	}

	/**
	 * Store new user
	 */
	public function store( array $Parameters ): string
	{
		$username = $_POST['username'] ?? '';
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		$role = $_POST['role'] ?? User::ROLE_SUBSCRIBER;

		// Validate
		if( empty( $username ) || empty( $email ) || empty( $password ) )
		{
			$this->_SessionManager->flash( 'error', 'All fields are required' );
			header( 'Location: /admin/users/create' );
			exit;
		}

		if( !$this->_Hasher->meetsRequirements( $password ) )
		{
			$this->_SessionManager->flash( 'error', 'Password does not meet requirements' );
			header( 'Location: /admin/users/create' );
			exit;
		}

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $this->_Hasher->hash( $password ) );
		$user->setRole( $role );
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );

		try
		{
			$this->_Repository->create( $user );
			$this->_SessionManager->flash( 'success', 'User created successfully' );
		}
		catch( \Exception $e )
		{
			$this->_SessionManager->flash( 'error', 'Failed to create user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}

	/**
	 * Show edit user form
	 */
	public function edit( array $Parameters ): string
	{
		$id = (int)$Parameters['id'];
		$User = Registry::getInstance()->get( 'Auth.User' );
		$user = $this->_Repository->findById( $id );

		if( !$user )
		{
			$this->_SessionManager->flash( 'error', 'User not found' );
			header( 'Location: /admin/users' );
			exit;
		}

		// Generate CSRF token
		$CsrfManager = new CsrfTokenManager( $this->_SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Edit User | ' . $this->getName(),
			'Description' => 'Edit User',
			'User' => $User,
			'user' => $user,
			'roles' => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR, User::ROLE_SUBSCRIBER]
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Users' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $View->render( $ViewData );
	}

	/**
	 * Update user
	 */
	public function update( array $Parameters ): string
	{
		$id = (int)$Parameters['id'];
		$user = $this->_Repository->findById( $id );

		if( !$user )
		{
			$this->_SessionManager->flash( 'error', 'User not found' );
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
			if( !$this->_Hasher->meetsRequirements( $password ) )
			{
				$this->_SessionManager->flash( 'error', 'Password does not meet requirements' );
				header( 'Location: /admin/users/' . $id . '/edit' );
				exit;
			}

			$user->setPasswordHash( $this->_Hasher->hash( $password ) );
		}

		try
		{
			$this->_Repository->update( $user );
			$this->_SessionManager->flash( 'success', 'User updated successfully' );
		}
		catch( \Exception $e )
		{
			$this->_SessionManager->flash( 'error', 'Failed to update user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}

	/**
	 * Delete user
	 */
	public function destroy( array $Parameters ): string
	{
		$id = (int)$Parameters['id'];
		$CurrentUser = Registry::getInstance()->get( 'Auth.User' );

		// Prevent self-deletion
		if( $CurrentUser && $CurrentUser->getId() === $id )
		{
			$this->_SessionManager->flash( 'error', 'Cannot delete your own account' );
			header( 'Location: /admin/users' );
			exit;
		}

		try
		{
			$this->_Repository->delete( $id );
			$this->_SessionManager->flash( 'success', 'User deleted successfully' );
		}
		catch( \Exception $e )
		{
			$this->_SessionManager->flash( 'error', 'Failed to delete user: ' . $e->getMessage() );
		}

		header( 'Location: /admin/users' );
		exit;
	}

	/**
	 * Get database configuration from settings
	 */
	private function getDatabaseConfig( SettingManager $Settings ): array
	{
		$config = [];
		$settingNames = $Settings->getSectionSettingNames( 'database' );

		foreach( $settingNames as $name )
		{
			$value = $Settings->get( 'database', $name );
			if( $value !== null )
			{
				$config[$name] = ( $name === 'port' ) ? (int)$value : $value;
			}
		}

		return $config;
	}
}
