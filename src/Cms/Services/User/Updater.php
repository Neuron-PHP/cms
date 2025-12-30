<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Events\UserUpdatedEvent;
use Neuron\Events\Emitter;
use Neuron\Dto\Dto;

/**
 * User update service.
 *
 * Updates existing users and their passwords.
 *
 * @package Neuron\Cms\Services\User
 */
class Updater implements IUserUpdater
{
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private ?Emitter $_eventEmitter;

	public function __construct(
		IUserRepository $userRepository,
		PasswordHasher $passwordHasher,
		?Emitter $eventEmitter = null
	)
	{
		$this->_userRepository = $userRepository;
		$this->_passwordHasher = $passwordHasher;
		$this->_eventEmitter = $eventEmitter;
	}

	/**
	 * Update an existing user from DTO
	 *
	 * @param Dto $request DTO containing id, username, email, role, password (optional)
	 * @return User
	 * @throws \Exception If user not found, password doesn't meet requirements, or update fails
	 */
	public function update( Dto $request ): User
	{
		// Extract values from DTO
		$id = $request->id;
		$username = $request->username;
		$email = $request->email;
		$role = $request->role;
		$password = $request->password ?? null;

		// Look up the user
		$user = $this->_userRepository->findById( $id );
		if( !$user )
		{
			throw new \Exception( "User with ID {$id} not found" );
		}

		// If password is provided, validate and hash it
		if( $password !== null && $password !== '' )
		{
			if( !$this->_passwordHasher->meetsRequirements( $password ) )
			{
				$errors = $this->_passwordHasher->getValidationErrors( $password );
				throw new \Exception( 'Password does not meet requirements: ' . implode( ', ', $errors ) );
			}
			$user->setPasswordHash( $this->_passwordHasher->hash( $password ) );
		}

		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setRole( $role );

		$user->setUpdatedAt( new \DateTimeImmutable() );

		$this->_userRepository->update( $user );

		// Emit user updated event
		if( $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new UserUpdatedEvent( $user ) );
		}

		return $user;
	}
}
