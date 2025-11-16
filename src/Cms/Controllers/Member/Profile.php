<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Member profile management controller.
 *
 * @package Neuron\Cms\Controllers\Member
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
	 *
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
		$csrfToken = new CsrfToken( $this->getSessionManager() );
		Registry::getInstance()->set( 'Auth.CsrfToken', $csrfToken->getToken() );

		// Get available timezones grouped by region with selection state
		$timezones = \DateTimeZone::listIdentifiers();
		$groupedTimezones = group_timezones_for_select( $timezones, $user->getTimezone() );

		$viewData = [
			'Title' => 'Profile | ' . $this->getName(),
			'Description' => 'Edit Your Profile',
			'User' => $user,
			'groupedTimezones' => $groupedTimezones,
			'success' => $this->getSessionManager()->getFlash( 'success' ),
			'error' => $this->getSessionManager()->getFlash( 'error' )
		];

		return $this->renderHtml(
			HttpResponseStatus::OK,
			$viewData,
			'edit',
			'member'
		);
	}

	/**
	 * Update profile
	 *
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

		// Security: Only use email from POST if provided by Account Information form
		// Password change form doesn't include email field, preventing email hijacking attacks
		$email = $request->post( 'email', $user->getEmail() );
		$timezone = $request->post( 'timezone', '' );
		$currentPassword = $request->post( 'current_password', '' );
		$newPassword = $request->post( 'new_password', '' );
		$confirmPassword = $request->post( 'confirm_password', '' );

		// Validate password change if requested
		if( !empty( $newPassword ) )
		{
			// Verify current password
			if( empty( $currentPassword ) || !$this->_hasher->verify( $currentPassword, $user->getPasswordHash() ) )
			{
				$this->redirect( 'member_profile', [], ['error', 'Current password is incorrect'] );
			}

			// Validate new password matches confirmation
			if( $newPassword !== $confirmPassword )
			{
				$this->redirect( 'member_profile', [], ['error', 'New passwords do not match'] );
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
			$this->redirect( 'member_profile', [], ['success', 'Profile updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'member_profile', [], ['error', $e->getMessage()] );
		}
	}
}
