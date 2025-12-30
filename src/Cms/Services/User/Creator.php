<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Events\Emitter;
use Neuron\Dto\Dto;
use DateTimeImmutable;
use Neuron\Cms\Enums\UserStatus;

/**
 * User creation service.
 *
 * Creates new users with password hashing and validation.
 *
 * @package Neuron\Cms\Services\User
 */
class Creator implements IUserCreator
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
	 * Create a new user from DTO
	 *
	 * @param Dto $request DTO containing username, email, password, role, timezone
	 * @return User
	 * @throws \Exception If password doesn't meet requirements or user creation fails
	 */
	public function create( Dto $request ): User
	{
		// Extract values from DTO
		$username = $request->username;
		$email = $request->email;
		$password = $request->password;
		$role = $request->role;
		$timezone = $request->timezone ?? null;

		// Validate password meets requirements
		if( !$this->_passwordHasher->meetsRequirements( $password ) )
		{
			$errors = $this->_passwordHasher->getValidationErrors( $password );
			throw new \Exception( 'Password does not meet requirements: ' . implode( ', ', $errors ) );
		}

		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $this->_passwordHasher->hash( $password ) );
		$user->setRole( $role );
		$user->setStatus( UserStatus::ACTIVE->value );
		$user->setEmailVerified( true );
		$user->setCreatedAt( new DateTimeImmutable() );

		// Set timezone if provided
		if( $timezone !== null && $timezone !== '' )
		{
			$user->setTimezone( $timezone );
		}

		$user = $this->_userRepository->create( $user );

		// Emit user created event
		if( $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new UserCreatedEvent( $user ) );
		}

		return $user;
	}
}
