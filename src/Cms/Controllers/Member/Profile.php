<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Member profile management controller.
 *
 * @package Neuron\Cms\Controllers\Member
 */
#[RouteGroup(prefix: '/member', filters: ['member'])]
class Profile extends Content
{
	private IUserRepository $_repository;
	private PasswordHasher $_hasher;
	private IUserUpdater $_userUpdater;

	/**
	 * @param Application|null $app
	 * @param IUserRepository|null $repository
	 * @param PasswordHasher|null $hasher
	 * @param IUserUpdater|null $userUpdater
	 * @param SettingManager|null $settings
	 * @param SessionManager|null $sessionManager
	 * @throws \Exception
	 */
	public function __construct(
		?Application $app = null,
		?IUserRepository $repository = null,
		?PasswordHasher $hasher = null,
		?IUserUpdater $userUpdater = null,
		?SettingManager $settings = null,
		?SessionManager $sessionManager = null
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
	 *
	 * @param Request $request
	 * @return string
	 * @throws \Exception
	 */
	#[Get('/profile', name: 'member_profile')]
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
			->with( 'groupedTimezones', $groupedTimezones )
			->with( 'success', $this->getSessionManager()->getFlash( 'success' ) )
			->with( 'error', $this->getSessionManager()->getFlash( 'error' ) )
			->render( 'edit', 'member' );
	}

	/**
	 * Update profile
	 *
	 * @param Request $request
	 * @return never
	 * @throws \Exception
	 */
	#[Put('/profile', name: 'member_profile_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		// Get authenticated user once and check for null
		$user = auth();
		if( !$user )
		{
			$this->redirect( 'member_profile', [], ['error', 'Authenticated user not found'] );
		}

		// Create DTO from YAML configuration
		$dto = $this->createDto( 'members/update-profile-request.yaml' );

		// Map request data to DTO
		$this->mapRequestToDto( $dto, $request );

		// Set ID from authenticated user (security: prevent users from changing other profiles)
		$dto->id = $user->getId();

		// Security: Only use email from POST if provided by Account Information form
		// Password change form doesn't include email field, preventing email hijacking attacks
		if( !$dto->email )
		{
			$dto->email = $user->getEmail();
		}

		// Validate DTO
		if( !$dto->validate() )
		{
			$errors = implode( ', ', $dto->getErrors() );
			$this->redirect( 'member_profile', [], ['error', $errors] );
		}

		// Validate password change if requested
		if( $dto->new_password )
		{
			// Verify current password is provided
			if( !$dto->current_password )
			{
				$this->redirect( 'member_profile', [], ['error', 'Current password is required to change password'] );
			}

			// Verify current password
			if( !$this->_hasher->verify( $dto->current_password, $user->getPasswordHash() ) )
			{
				$this->redirect( 'member_profile', [], ['error', 'Current password is incorrect'] );
			}

			// Validate new password matches confirmation
			if( !$dto->confirm_password || $dto->new_password !== $dto->confirm_password )
			{
				$this->redirect( 'member_profile', [], ['error', 'New passwords do not match'] );
			}
		}

		try
		{
			// Create admin update DTO for the updater service
			$updateDto = $this->createDto( 'users/update-user-request.yaml' );
			$updateDto->id = $user->getId();
			$updateDto->username = $user->getUsername();  // Can't change own username
			$updateDto->email = $dto->email;
			$updateDto->role = $user->getRole();  // Preserve current role (security)

			// Only set password if provided
			if( $dto->new_password )
			{
				$updateDto->password = $dto->new_password;
			}

			// Call updater with DTO
			$this->_userUpdater->update( $updateDto );

			// Update timezone separately if provided (not in user updater)
			if( $dto->timezone )
			{
				$user->setTimezone( $dto->timezone );
				$this->_repository->update( $user );
			}

			$this->redirect( 'member_profile', [], ['success', 'Profile updated successfully'] );
		}
		catch( \Exception $e )
		{
			$this->redirect( 'member_profile', [], ['error', $e->getMessage()] );
		}
	}
}
