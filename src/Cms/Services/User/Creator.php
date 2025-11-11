<?php

namespace Neuron\Cms\Services\User;

use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Patterns\Registry;
use DateTimeImmutable;

/**
 * User creation service.
 *
 * Creates new users with password hashing and validation.
 *
 * @package Neuron\Cms\Services\User
 */
class Creator
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
	 * Create a new user
	 *
	 * @param string $username Username
	 * @param string $email Email address
	 * @param string $password Plain text password
	 * @param string $role User role (admin, editor, author, subscriber)
	 * @return User
	 * @throws \Exception If password doesn't meet requirements or user creation fails
	 */
	public function create(
		string $username,
		string $email,
		string $password,
		string $role
	): User
	{
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
		$user->setStatus( User::STATUS_ACTIVE );
		$user->setEmailVerified( true );
		$user->setCreatedAt( new DateTimeImmutable() );

		$user = $this->_userRepository->create( $user );

		// Emit user created event
		$emitter = Registry::getInstance()->get( 'EventEmitter' );
		if( $emitter )
		{
			$emitter->emit( new UserCreatedEvent( $user ) );
		}

		return $user;
	}
}
