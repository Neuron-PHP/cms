<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
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
 * User profile management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class ProfileController extends Content
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
	 * Show profile edit form
	 */
	public function edit( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$CsrfManager = new CsrfTokenManager( $this->_SessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $CsrfManager->getToken() );

		$ViewData = [
			'Title' => 'Profile | ' . $this->getName(),
			'Description' => 'Edit Your Profile',
			'User' => $User,
			'success' => $this->_SessionManager->getFlash( 'success' ),
			'error' => $this->_SessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$View = new Html();
		$View->setController( 'Admin/Profile' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $View->render( $ViewData );
	}

	/**
	 * Update profile
	 */
	public function update( array $Parameters ): string
	{
		$User = Registry::getInstance()->get( 'Auth.User' );

		if( !$User )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$email = $_POST['email'] ?? '';
		$currentPassword = $_POST['current_password'] ?? '';
		$newPassword = $_POST['new_password'] ?? '';
		$confirmPassword = $_POST['confirm_password'] ?? '';

		// Update email
		if( !empty( $email ) && $email !== $User->getEmail() )
		{
			$User->setEmail( $email );
		}

		// Update password if provided
		if( !empty( $newPassword ) )
		{
			// Verify current password
			if( empty( $currentPassword ) || !$this->_Hasher->verify( $currentPassword, $User->getPasswordHash() ) )
			{
				$this->_SessionManager->flash( 'error', 'Current password is incorrect' );
				header( 'Location: /admin/profile' );
				exit;
			}

			// Validate new password
			if( $newPassword !== $confirmPassword )
			{
				$this->_SessionManager->flash( 'error', 'New passwords do not match' );
				header( 'Location: /admin/profile' );
				exit;
			}

			if( !$this->_Hasher->meetsRequirements( $newPassword ) )
			{
				$this->_SessionManager->flash( 'error', 'Password does not meet requirements' );
				header( 'Location: /admin/profile' );
				exit;
			}

			$User->setPasswordHash( $this->_Hasher->hash( $newPassword ) );
		}

		try
		{
			$this->_Repository->update( $User );
			$this->_SessionManager->flash( 'success', 'Profile updated successfully' );
		}
		catch( \Exception $e )
		{
			$this->_SessionManager->flash( 'error', 'Failed to update profile: ' . $e->getMessage() );
		}

		header( 'Location: /admin/profile' );
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
