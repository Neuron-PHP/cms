<?php

namespace Neuron\Cms\Auth;

use InvalidArgumentException;
use Neuron\Log\Log;

/**
 * Password hashing and validation utility.
 *
 * Uses PHP's native password_hash() with Argon2id algorithm
 * for secure password storage and verification.
 *
 * @package Neuron\Cms\Auth
 */
class PasswordHasher
{
	private int $_minLength = 8;
	private bool $_requireUppercase = true;
	private bool $_requireLowercase = true;
	private bool $_requireNumbers = true;
	private bool $_requireSpecialChars = false;

	public const ARGON2ID = 'argon2id';
	public const BCRYPT = 'bcrypt';

	/** Configured algorithm; null = argon2id when available, else bcrypt */
	private ?string $_algorithm = null;

	/**
	 * Hash a password with the configured algorithm
	 */
	public function hash( string $password ): string
	{
		return password_hash( $password, $this->resolveAlgorithm() );
	}

	/**
	 * Verify a password against a hash
	 */
	public function verify( string $password, string $hash ): bool
	{
		return password_verify( $password, $hash );
	}

	/**
	 * Check if a hash needs to be rehashed (algorithm upgrade)
	 */
	public function needsRehash( string $hash ): bool
	{
		return password_needs_rehash( $hash, $this->resolveAlgorithm() );
	}

	/**
	 * Choose the hash algorithm: 'argon2id' or 'bcrypt'. Existing hashes made
	 * with the other algorithm are upgraded at the user's next login.
	 *
	 * @throws InvalidArgumentException For any other name
	 */
	public function setAlgorithm( string $algorithm ): self
	{
		$algorithm = strtolower( trim( $algorithm ) );

		if( !in_array( $algorithm, [ self::ARGON2ID, self::BCRYPT ], true ) )
		{
			throw new InvalidArgumentException( "Unsupported password hash algorithm '$algorithm' (use argon2id or bcrypt)" );
		}

		$this->_algorithm = $algorithm;
		return $this;
	}

	/**
	 * The algorithm name in effect: argon2id or bcrypt
	 */
	public function getAlgorithm(): string
	{
		return $this->resolveAlgorithm() === PASSWORD_BCRYPT ? self::BCRYPT : self::ARGON2ID;
	}

	/**
	 * @return string PHP password algorithm constant
	 */
	private function resolveAlgorithm(): string
	{
		$argonAvailable = defined( 'PASSWORD_ARGON2ID' );

		if( $this->_algorithm === self::BCRYPT )
		{
			return PASSWORD_BCRYPT;
		}

		if( $this->_algorithm === self::ARGON2ID && !$argonAvailable )
		{
			static $warned = false;

			if( !$warned )
			{
				Log::warning( 'hash_algorithm argon2id is not available in this PHP build; using bcrypt.' );
				$warned = true;
			}
		}

		return $argonAvailable ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
	}

	/**
	 * Check if password meets strength requirements
	 */
	public function meetsRequirements( string $password ): bool
	{
		// Check minimum length
		if( strlen( $password ) < $this->_minLength )
		{
			return false;
		}

		// Check for uppercase letters
		if( $this->_requireUppercase && !preg_match( '/[A-Z]/', $password ) )
		{
			return false;
		}

		// Check for lowercase letters
		if( $this->_requireLowercase && !preg_match( '/[a-z]/', $password ) )
		{
			return false;
		}

		// Check for numbers
		if( $this->_requireNumbers && !preg_match( '/[0-9]/', $password ) )
		{
			return false;
		}

		// Check for special characters
		if( $this->_requireSpecialChars && !preg_match( '/[^A-Za-z0-9]/', $password ) )
		{
			return false;
		}

		return true;
	}

	/**
	 * Get password validation error messages
	 *
	 * @return list<string>
	 */
	public function getValidationErrors( string $password ): array
	{
		$errors = [];

		if( strlen( $password ) < $this->_minLength )
		{
			$errors[] = "Password must be at least {$this->_minLength} characters long";
		}

		if( $this->_requireUppercase && !preg_match( '/[A-Z]/', $password ) )
		{
			$errors[] = 'Password must contain at least one uppercase letter';
		}

		if( $this->_requireLowercase && !preg_match( '/[a-z]/', $password ) )
		{
			$errors[] = 'Password must contain at least one lowercase letter';
		}

		if( $this->_requireNumbers && !preg_match( '/[0-9]/', $password ) )
		{
			$errors[] = 'Password must contain at least one number';
		}

		if( $this->_requireSpecialChars && !preg_match( '/[^A-Za-z0-9]/', $password ) )
		{
			$errors[] = 'Password must contain at least one special character';
		}

		return $errors;
	}

	/**
	 * Set minimum password length
	 */
	public function setMinLength( int $minLength ): self
	{
		$this->_minLength = $minLength;
		return $this;
	}

	/**
	 * Set whether uppercase letters are required
	 */
	public function setRequireUppercase( bool $requireUppercase ): self
	{
		$this->_requireUppercase = $requireUppercase;
		return $this;
	}

	/**
	 * Set whether lowercase letters are required
	 */
	public function setRequireLowercase( bool $requireLowercase ): self
	{
		$this->_requireLowercase = $requireLowercase;
		return $this;
	}

	/**
	 * Set whether numbers are required
	 */
	public function setRequireNumbers( bool $requireNumbers ): self
	{
		$this->_requireNumbers = $requireNumbers;
		return $this;
	}

	/**
	 * Set whether special characters are required
	 */
	public function setRequireSpecialChars( bool $requireSpecialChars ): self
	{
		$this->_requireSpecialChars = $requireSpecialChars;
		return $this;
	}

	/**
	 * Configure password requirements from settings
	 *
	 * @param array<string, mixed> $settings
	 */
	public function configure( array $settings ): self
	{
		if( isset( $settings['min_length'] ) )
		{
			$this->setMinLength( $settings['min_length'] );
		}

		if( isset( $settings['require_uppercase'] ) )
		{
			$this->setRequireUppercase( $settings['require_uppercase'] );
		}

		if( isset( $settings['require_lowercase'] ) )
		{
			$this->setRequireLowercase( $settings['require_lowercase'] );
		}

		if( isset( $settings['require_numbers'] ) )
		{
			$this->setRequireNumbers( $settings['require_numbers'] );
		}

		if( isset( $settings['require_special_chars'] ) )
		{
			$this->setRequireSpecialChars( $settings['require_special_chars'] );
		}

		return $this;
	}
}
