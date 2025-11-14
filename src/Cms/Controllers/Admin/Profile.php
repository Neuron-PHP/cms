<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Data\Setting\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * User profile management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
class Profile extends Content
{
	private DatabaseUserRepository $_repository;
	private PasswordHasher $_hasher;
	private Updater $_userUpdater;

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

		// Initialize service
		$this->_userUpdater = new Updater( $this->_repository, $this->_hasher );
	}

	/**
	 * Show profile edit form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( Request $request ): string
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Generate CSRF token
		$csrfManager = new CsrfTokenManager( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfManager->getToken() );

		// Get available timezones grouped by region
		$timezones = \DateTimeZone::listIdentifiers();

		$viewData = [
			'Title' => 'Profile | ' . $this->getName(),
			'Description' => 'Edit Your Profile',
			'User' => $user,
			'timezones' => $timezones,
			'success' => $this->getSessionManager()->getFlash( 'success' ),
			'error' => $this->getSessionManager()->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'admin'
		);
	}

	/**
	 * Update profile
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		$user = Registry::getInstance()->get( 'Auth.User' );

		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		$email = $request->post( 'email', '' );
		$timezone = $request->post( 'timezone',  '' );
		$currentPassword = $request->post( 'current_password', '' );
		$newPassword = $request->post( 'new_password', '' );
		$confirmPassword = $request->post( 'confirm_password', '' );

		// Validate password change if requested
		if( !empty( $newPassword ) )
		{
			// Verify current password
			if( empty( $currentPassword ) || !$this->_hasher->verify( $currentPassword, $user->getPasswordHash() ) )
			{
				$this->redirect( 'admin_profile', [], ['error', 'Current password is incorrect'] );
			}

			// Validate new password matches confirmation
			if( $newPassword !== $confirmPassword )
			{
				$this->redirect( 'admin_profile', [], ['error', 'New passwords do not match'] );
			}
		}

		try
		{
			$this->_userUpdater->update(
				$user,
				$user->getUsername(),
				$email,
				$user->getRole(),
				!empty( $newPassword ) ? $newPassword : null,
				!empty( $timezone ) ? $timezone : null
			);
			$this->redirect( 'admin_profile', [], ['success', 'Profile updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_profile', [], ['error', $e->getMessage()] );
		}
	}
}
