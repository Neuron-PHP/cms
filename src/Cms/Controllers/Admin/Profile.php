<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\Traits\UsesDtos;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * User profile management controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Profile extends Content
{
	use UsesDtos;

	private IUserRepository $_repository;
	private PasswordHasher $_hasher;
	private IUserUpdater $_userUpdater;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IUserRepository|null $repository
	 * @param PasswordHasher|null $hasher
	 * @param IUserUpdater|null $userUpdater
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IUserRepository $repository = null,
		?PasswordHasher $hasher = null,
		?IUserUpdater $userUpdater = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		if( $repository === null )
		{
			throw new \InvalidArgumentException( 'IUserRepository must be injected' );
		}
		$this->_repository = $repository;

		if( $hasher === null )
		{
			throw new \InvalidArgumentException( 'PasswordHasher must be injected' );
		}
		$this->_hasher = $hasher;

		if( $userUpdater === null )
		{
			throw new \InvalidArgumentException( 'IUserUpdater must be injected' );
		}
		$this->_userUpdater = $userUpdater;
	}

	/**
	 * Show profile edit form
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/profile', name: 'admin_profile')]
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
	#[Put('/profile', name: 'admin_profile_update', filters: ['csrf'])]
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
			// Create and populate DTO for update request
			$dto = $this->createDto( 'users/update-user-request.yaml' );
			$dto->id = $user->getId();
			$dto->username = $user->getUsername();
			$dto->email = $email;
			$dto->role = $user->getRole();

			if( !empty( $newPassword ) )
			{
				$dto->password = $newPassword;
			}

			if( !empty( $timezone ) )
			{
				$dto->timezone = $timezone;
			}

			// Validate and update
			$this->validateDtoOrFail( $dto );
			$this->_userUpdater->update( $dto );

			$this->redirect( 'admin_profile', [], [FlashMessageType::SUCCESS->value, 'Profile updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'admin_profile', [], [FlashMessageType::ERROR->value, $e->getMessage()] );
		}
	}
}
