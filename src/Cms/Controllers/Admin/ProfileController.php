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
	 * Show profile edit form
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function edit( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfManager = new CsrfTokenManager( $this->_sessionManager );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		$viewData = [
			'Title' => 'Profile | ' . $this->getName(),
			'Description' => 'Edit Your Profile',
			'User' => $user,
			'success' => $this->_sessionManager->getFlash( 'success' ),
			'error' => $this->_sessionManager->getFlash( 'error' )
		];

		@http_response_code( HttpResponseStatus::OK->value );

		$view = new Html();
		$view->setController( 'Admin/Profile' )
			 ->setLayout( 'admin' )
			 ->setPage( 'edit' );

		return $view->render( $viewData );
	}

	/**
	 * Update profile
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	public function update( array $parameters ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$email = $_POST['email'] ?? '';
		$currentPassword = $_POST['current_password'] ?? '';
		$newPassword = $_POST['new_password'] ?? '';
		$confirmPassword = $_POST['confirm_password'] ?? '';

		// Update email
		if( !empty( $email ) && $email !== $user->getEmail() )
		{
			$user->setEmail( $email );
		}

		// Update password if provided
		if( !empty( $newPassword ) )
		{
			// Verify current password
			if( empty( $currentPassword ) || !$this->_hasher->verify( $currentPassword, $user->getPasswordHash() ) )
			{
				$this->_sessionManager->flash( 'error', 'Current password is incorrect' );
				header( 'Location: /admin/profile' );
				exit;
			}

			// Validate new password
			if( $newPassword !== $confirmPassword )
			{
				$this->_sessionManager->flash( 'error', 'New passwords do not match' );
				header( 'Location: /admin/profile' );
				exit;
			}

			if( !$this->_hasher->meetsRequirements( $newPassword ) )
			{
				$this->_sessionManager->flash( 'error', 'Password does not meet requirements' );
				header( 'Location: /admin/profile' );
				exit;
			}

			$user->setPasswordHash( $this->_hasher->hash( $newPassword ) );
		}

		if( $this->_repository->update( $user ) )
		{
			$this->_sessionManager->flash( 'success', 'Profile updated successfully' );
		}
		else
		{
			$this->_sessionManager->flash( 'error', 'Failed to update profile' );
		}

		header( 'Location: /admin/profile' );
		exit;
	}
}
