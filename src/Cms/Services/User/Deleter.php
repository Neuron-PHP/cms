<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Events\UserDeletedEvent;
use Neuron\Events\Emitter;

/**
 * User deletion service.
 *
 * Handles safe deletion of users.
 *
 * @package Neuron\Cms\Services\User
 */
class Deleter implements IUserDeleter
{
	private IUserRepository $_userRepository;
	private ?Emitter $_eventEmitter;

	public function __construct(
		IUserRepository $userRepository,
		?Emitter $eventEmitter = null
	)
	{
		$this->_userRepository = $userRepository;
		$this->_eventEmitter = $eventEmitter;
	}

	/**
	 * Delete a user by ID
	 *
	 * @param int $userId User ID to delete
	 * @return bool True if deletion was successful
	 * @throws \Exception If user cannot be deleted
	 */
	public function delete( int $userId ): bool
	{
		$user = $this->_userRepository->findById( $userId );

		if( !$user )
		{
			throw new \Exception( 'User not found' );
		}

		$result = $this->_userRepository->delete( $userId );

		// Emit user deleted event
		if( $result && $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new UserDeletedEvent( $userId ) );
		}

		return $result;
	}
}
