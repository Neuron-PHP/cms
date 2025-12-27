<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Enums\FlashMessageType;
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

		// Get settings if we need to create repository
		if( $repository === null )
		{
			$settings = Registry::getInstance()->get( 'Settings' );
			$repository = new DatabaseUserRepository( $settings );
		}

		// Create hasher if not provided
		if( $hasher === null )
		{
			$hasher = new PasswordHasher();
		}

		// Create updater if not provided
		if( $userUpdater === null )
		{
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

		// Get authenticated user once
		$user = auth();
		if( !$user )
		{
			throw new \RuntimeException( 'Authenticated user not found' );
		}

		// Get available timezones grouped by region with selection state
		$timezones = \DateTimeZone::listIdentifiers();
		$groupedTimezones = group_timezones_for_select( $timezones, $user->getTimezone() );

		return $this->view()
			->title( 'Profile' )
			->description( 'Edit Your Profile' )
			->withCurrentUser()
			->withCsrfToken()
			->with([
				'groupedTimezones' => $groupedTimezones,
				FlashMessageType::SUCCESS->value => $this->getSessionManager()->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->value => $this->getSessionManager()->getFlash( FlashMessageType::ERROR->value )
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
		// Get authenticated user once and check for null
		$user = auth();
		if( !$user )
		{
			$this->redirect( 'admin_profile', [], [FlashMessageType::ERROR->value, 'Authenticated user not found'] );
		}

		// Security: Only use email from POST if provided by Account Information form
		// Password change form doesn't include email field, preventing email hijacking attacks
		$email = $request->post( 'email', $user->getEmail() );
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
				$this->redirect( 'admin_profile', [], [FlashMessageType::ERROR->value, 'Current password is incorrect'] );
			}

			// Validate new password matches confirmation
			if( $newPassword !== $confirmPassword )
			{
				$this->redirect( 'admin_profile', [], [FlashMessageType::ERROR->value, 'New passwords do not match'] );
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
			$this->redirect( 'admin_profile', [], [FlashMessageType::SUCCESS->value, 'Profile updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_profile', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}
}
