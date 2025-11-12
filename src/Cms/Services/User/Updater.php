<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Events\UserUpdatedEvent;
use Neuron\Patterns\Registry;

/**
 * User update service.
 *
 * Updates existing users and their passwords.
 *
 * @package Neuron\Cms\Services\User
 */
class Updater
{
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;

	public function __construct(
		IUserRepository $userRepository,
		PasswordHasher $passwordHasher
	)
	{
		$this->_userRepository = $userRepository;
		$this->_passwordHasher = $passwordHasher;
	}

	/**
	 * Update an existing user
	 *
	 * @param User $user The user to update
	 * @param string $username Username
	 * @param string $email Email address
	 * @param string $role User role
	 * @param string|null $password Optional new password (if provided, will be validated and hashed)
	 * @param string|null $timezone Optional user timezone
	 * @return User
	 * @throws \Exception If password doesn't meet requirements or update fails
	 */
	public function update(
		User $user,
		string $username,
		string $email,
		string $role,
		?string $password = null,
		?string $timezone = null
	): User
	{
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

		// Update timezone if provided
		if( $timezone !== null && $timezone !== '' )
		{
			$user->setTimezone( $timezone );
		}

		$user->setUpdatedAt( new \DateTimeImmutable() );

		$this->_userRepository->update( $user );

		// Emit user updated event
		$emitter = Registry::getInstance()->get( 'EventEmitter' );
		if( $emitter )
		{
			$emitter->emit( new UserUpdatedEvent( $user ) );
		}

		return $user;
	}
}
