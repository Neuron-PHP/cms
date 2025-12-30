<?php

namespace Neuron\Cms\Services\Member;

use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Dto\Dto;
use Neuron\Events\Emitter;
use Neuron\Cms\Events\UserCreatedEvent;
use Exception;
use Neuron\Cms\Enums\UserRole;
use Neuron\Cms\Enums\UserStatus;

/**
 * Member registration service.
 *
 * Handles user registration, validation, and email verification.
 *
 * @package Neuron\Cms\Services\Member
 */
class RegistrationService implements IRegistrationService
{
	private IUserRepository $_userRepository;
	private PasswordHasher $_passwordHasher;
	private EmailVerifier $_emailVerifier;
	private SettingManager $_settings;
	private ?Emitter $_emitter;

	/**
	 * Constructor
	 *
	 * @param IUserRepository $userRepository User repository
	 * @param PasswordHasher $passwordHasher Password hasher
	 * @param EmailVerifier $emailVerifier Email verification service
	 * @param SettingManager $settings Settings manager
	 * @param Emitter|null $emitter Event emitter (optional)
	 */
	public function __construct(
		IUserRepository $userRepository,
		PasswordHasher $passwordHasher,
		EmailVerifier $emailVerifier,
		SettingManager $settings,
		?Emitter $emitter = null
	)
	{
		$this->_userRepository = $userRepository;
		$this->_passwordHasher = $passwordHasher;
		$this->_emailVerifier = $emailVerifier;
		$this->_settings = $settings;
		$this->_emitter = $emitter;
	}

	/**
	 * Check if registration is enabled
	 *
	 * @return bool True if registration is enabled, false otherwise
	 */
	public function isRegistrationEnabled(): bool
	{
		return $this->_settings->get( 'member', 'registration_enabled' ) ?? true;
	}

	/**
	 * Register a new user
	 *
	 * @param string $username Username
	 * @param string $email Email address
	 * @param string $password Password
	 * @param string $passwordConfirmation Password confirmation
	 * @return User Created user
	 * @throws Exception if registration is disabled or validation fails
	 */
	public function register(
		string $username,
		string $email,
		string $password,
		string $passwordConfirmation
	): User
	{
		// Check if registration is enabled
		if( !$this->isRegistrationEnabled() )
		{
			throw new Exception( 'User registration is currently disabled.' );
		}

		// Validate input
		$this->validateRegistration( $username, $email, $password, $passwordConfirmation );

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $this->_passwordHasher->hash( $password ) );

		// Set default role from settings
		$defaultRole = $this->_settings->get( 'member', 'default_role' ) ?? UserRole::SUBSCRIBER->value;
		$user->setRole( $defaultRole );

		// Determine if email verification is required
		$requireVerification = $this->_settings->get( 'member', 'require_email_verification' ) ?? true;

		if( $requireVerification )
		{
			// User starts as inactive until email is verified
			$user->setStatus( UserStatus::INACTIVE->value );
			$user->setEmailVerified( false );
		}
		else
		{
			// User is immediately active if verification not required
			$user->setStatus( UserStatus::ACTIVE->value );
			$user->setEmailVerified( true );
		}

		// Create user in database
		$this->_userRepository->create( $user );

		// Send a verification email if required
		if( $requireVerification )
		{
			try
			{
				$this->_emailVerifier->sendVerificationEmail( $user );
			}
			catch( Exception $e )
			{
				// Log error but don't fail registration
				// User can request resend later
			}
		}

		// Emit user created event
		if( $this->_emitter )
		{
			$this->_emitter->emit( new UserCreatedEvent( $user ) );
		}

		return $user;
	}

	/**
	 * Register a new user using a RegisterUser DTO
	 *
	 * @param Dto $dto RegisterUser DTO with validated data
	 * @return User Created user
	 * @throws Exception if registration is disabled or validation fails
	 */
	public function registerWithDto( Dto $dto ): User
	{
		// Check if registration is enabled
		if( !$this->isRegistrationEnabled() )
		{
			throw new Exception( 'User registration is currently disabled.' );
		}

		// Extract data from DTO
		$username = $dto->username;
		$email = $dto->email;
		$password = $dto->password;
		$passwordConfirmation = $dto->password_confirmation;

		// Validate business rules (uniqueness checks)
		$this->validateUserBusinessRules( $username, $email );

		// Validate password confirmation
		if( $password !== $passwordConfirmation )
		{
			throw new Exception( 'Passwords do not match.' );
		}

		// Create user
		$user = new User();
		$user->setUsername( $username );
		$user->setEmail( $email );
		$user->setPasswordHash( $this->_passwordHasher->hash( $password ) );

		// Set default role from settings
		$defaultRole = $this->_settings->get( 'member', 'default_role' ) ?? UserRole::SUBSCRIBER->value;
		$user->setRole( $defaultRole );

		// Determine if email verification is required
		$requireVerification = $this->_settings->get( 'member', 'require_email_verification' ) ?? true;

		if( $requireVerification )
		{
			// User starts as inactive until email is verified
			$user->setStatus( UserStatus::INACTIVE->value );
			$user->setEmailVerified( false );
		}
		else
		{
			// User is immediately active if verification not required
			$user->setStatus( UserStatus::ACTIVE->value );
			$user->setEmailVerified( true );
		}

		// Create user in database
		$this->_userRepository->create( $user );

		// Send verification email if required
		if( $requireVerification )
		{
			try
			{
				$this->_emailVerifier->sendVerificationEmail( $user );
			}
			catch( Exception $e )
			{
				// Log error but don't fail registration
				// User can request resend later
			}
		}

		// Emit user created event
		if( $this->_emitter )
		{
			$this->_emitter->emit( new UserCreatedEvent( $user ) );
		}

		return $user;
	}

	/**
	 * Validate registration data
	 *
	 * @param string $username Username
	 * @param string $email Email address
	 * @param string $password Password
	 * @param string $passwordConfirmation Password confirmation
	 * @throws Exception if validation fails
	 */
	private function validateRegistration(
		string $username,
		string $email,
		string $password,
		string $passwordConfirmation
	): void
	{
		// Validate username
		if( empty( $username ) )
		{
			throw new Exception( 'Username is required.' );
		}

		if( strlen( $username ) < 3 || strlen( $username ) > 50 )
		{
			throw new Exception( 'Username must be between 3 and 50 characters.' );
		}

		if( !preg_match( '/^[a-zA-Z0-9_]+$/', $username ) )
		{
			throw new Exception( 'Username can only contain letters, numbers, and underscores.' );
		}

		// Check if username is already taken
		if( $this->_userRepository->findByUsername( $username ) )
		{
			throw new Exception( 'Username is already taken.' );
		}

		// Validate email
		if( empty( $email ) )
		{
			throw new Exception( 'Email is required.' );
		}

		if( !filter_var( $email, FILTER_VALIDATE_EMAIL ) )
		{
			throw new Exception( 'Invalid email address.' );
		}

		// Check if email is already taken
		if( $this->_userRepository->findByEmail( $email ) )
		{
			throw new Exception( 'Email is already registered.' );
		}

		// Validate password
		if( empty( $password ) )
		{
			throw new Exception( 'Password is required.' );
		}

		if( $password !== $passwordConfirmation )
		{
			throw new Exception( 'Passwords do not match.' );
		}

		// Validate password strength
		if( !$this->_passwordHasher->meetsRequirements( $password ) )
		{
			$errors = $this->_passwordHasher->getValidationErrors( $password );
			throw new Exception( implode( ', ', $errors ) );
		}
	}

	/**
	 * Validate user business rules (uniqueness checks)
	 *
	 * @param string $username Username to validate
	 * @param string $email Email to validate
	 * @throws Exception if business rules fail
	 */
	private function validateUserBusinessRules( string $username, string $email ): void
	{
		// Check if username is already taken
		if( $this->_userRepository->findByUsername( $username ) )
		{
			throw new Exception( 'Username is already taken.' );
		}

		// Check if email is already registered
		if( $this->_userRepository->findByEmail( $email ) )
		{
			throw new Exception( 'Email is already registered.' );
		}
	}
}
