<?php

namespace Neuron\Cms\Auth;

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

	/**
	 * Hash a password using Argon2id or Bcrypt
	 */
	public function hash( string $password ): string
	{
		// Use Argon2id if available, otherwise fall back to Bcrypt
		$algorithm = defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

		return password_hash( $password, $algorithm );
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
		$algorithm = defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

		return password_needs_rehash( $hash, $algorithm );
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
