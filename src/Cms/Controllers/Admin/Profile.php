<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Data\Settings\SettingManager;
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
	 * @param DatabaseUserRepository|null $repository
	 * @param PasswordHasher|null $hasher
	 * @param Updater|null $userUpdater
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?DatabaseUserRepository $repository = null,
		?PasswordHasher $hasher = null,
		?Updater $userUpdater = null
	)
	{
		parent::__construct( $app );

		// Use injected dependencies if provided (for testing), otherwise create them (for production)
		if( $repository === null )
		{
			// Get settings and initialize repository
			$settings = Registry::getInstance()->get( 'Settings' );
			$repository = new DatabaseUserRepository( $settings );
			$hasher = new PasswordHasher();

			// Initialize service
			$userUpdater = new Updater( $repository, $hasher );
		}

		$this->_repository = $repository;
		$this->_hasher = $hasher;
		$this->_userUpdater = $userUpdater;
	}

	/**
	 * Show profile edit form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	public function edit( Request $request ): string
	{
		$this->initializeCsrfToken();

		// Get available timezones grouped by region with selection state
		$timezones = \DateTimeZone::listIdentifiers();
		$groupedTimezones = group_timezones_for_select( $timezones, auth()->getTimezone() );

		return $this->view()
			->title( 'Profile' )
			->description( 'Edit Your Profile' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'groupedTimezones' => $groupedTimezones,
				'success' => $this->getSessionManager()->getFlash( 'success' ),
				'error' => $this->getSessionManager()->getFlash( 'error' )
			])
			->render( 'edit', 'admin' );
	}

	/**
	 * Update profile
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	public function update( Request $request ): never
	{
		// Security: Only use email from POST if provided by Account Information form
		// Password change form doesn't include email field, preventing email hijacking attacks
		$email = $request->post( 'email', auth()->getEmail() );
		$timezone = $request->post( 'timezone',  '' );
		$currentPassword = $request->post( 'current_password', '' );
		$newPassword = $request->post( 'new_password', '' );
		$confirmPassword = $request->post( 'confirm_password', '' );

		// Validate password change if requested
		if( !empty( $newPassword ) )
		{
			// Verify current password
			if( empty( $currentPassword ) || !$this->_hasher->verify( $currentPassword, auth()->getPasswordHash() ) )
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
				auth(),
				auth()->getUsername(),
				$email,
				auth()->getRole(),
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
