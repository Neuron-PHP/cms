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
	private int $_MinLength = 8;
	private bool $_RequireUppercase = true;
	private bool $_RequireLowercase = true;
	private bool $_RequireNumbers = true;
	private bool $_RequireSpecialChars = false;

	/**
	 * Hash a password using Argon2id or Bcrypt
	 */
	public function hash( string $Password ): string
	{
		// Use Argon2id if available, otherwise fall back to Bcrypt
		$algorithm = defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

		return password_hash( $Password, $algorithm );
	}

	/**
	 * Verify a password against a hash
	 */
	public function verify( string $Password, string $Hash ): bool
	{
		return password_verify( $Password, $Hash );
	}

	/**
	 * Check if a hash needs to be rehashed (algorithm upgrade)
	 */
	public function needsRehash( string $Hash ): bool
	{
		$algorithm = defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

		return password_needs_rehash( $Hash, $algorithm );
	}

	/**
	 * Check if password meets strength requirements
	 */
	public function meetsRequirements( string $Password ): bool
	{
		// Check minimum length
		if( strlen( $Password ) < $this->_MinLength )
		{
			return false;
		}

		// Check for uppercase letters
		if( $this->_RequireUppercase && !preg_match( '/[A-Z]/', $Password ) )
		{
			return false;
		}

		// Check for lowercase letters
		if( $this->_RequireLowercase && !preg_match( '/[a-z]/', $Password ) )
		{
			return false;
		}

		// Check for numbers
		if( $this->_RequireNumbers && !preg_match( '/[0-9]/', $Password ) )
		{
			return false;
		}

		// Check for special characters
		if( $this->_RequireSpecialChars && !preg_match( '/[^A-Za-z0-9]/', $Password ) )
		{
			return false;
		}

		return true;
	}

	/**
	 * Get password validation error messages
	 */
	public function getValidationErrors( string $Password ): array
	{
		$Errors = [];

		if( strlen( $Password ) < $this->_MinLength )
		{
			$Errors[] = "Password must be at least {$this->_MinLength} characters long";
		}

		if( $this->_RequireUppercase && !preg_match( '/[A-Z]/', $Password ) )
		{
			$Errors[] = 'Password must contain at least one uppercase letter';
		}

		if( $this->_RequireLowercase && !preg_match( '/[a-z]/', $Password ) )
		{
			$Errors[] = 'Password must contain at least one lowercase letter';
		}

		if( $this->_RequireNumbers && !preg_match( '/[0-9]/', $Password ) )
		{
			$Errors[] = 'Password must contain at least one number';
		}

		if( $this->_RequireSpecialChars && !preg_match( '/[^A-Za-z0-9]/', $Password ) )
		{
			$Errors[] = 'Password must contain at least one special character';
		}

		return $Errors;
	}

	/**
	 * Set minimum password length
	 */
	public function setMinLength( int $MinLength ): self
	{
		$this->_MinLength = $MinLength;
		return $this;
	}

	/**
	 * Set whether uppercase letters are required
	 */
	public function setRequireUppercase( bool $RequireUppercase ): self
	{
		$this->_RequireUppercase = $RequireUppercase;
		return $this;
	}

	/**
	 * Set whether lowercase letters are required
	 */
	public function setRequireLowercase( bool $RequireLowercase ): self
	{
		$this->_RequireLowercase = $RequireLowercase;
		return $this;
	}

	/**
	 * Set whether numbers are required
	 */
	public function setRequireNumbers( bool $RequireNumbers ): self
	{
		$this->_RequireNumbers = $RequireNumbers;
		return $this;
	}

	/**
	 * Set whether special characters are required
	 */
	public function setRequireSpecialChars( bool $RequireSpecialChars ): self
	{
		$this->_RequireSpecialChars = $RequireSpecialChars;
		return $this;
	}

	/**
	 * Configure password requirements from settings
	 */
	public function configure( array $Settings ): self
	{
		if( isset( $Settings['min_length'] ) )
		{
			$this->setMinLength( $Settings['min_length'] );
		}

		if( isset( $Settings['require_uppercase'] ) )
		{
			$this->setRequireUppercase( $Settings['require_uppercase'] );
		}

		if( isset( $Settings['require_lowercase'] ) )
		{
			$this->setRequireLowercase( $Settings['require_lowercase'] );
		}

		if( isset( $Settings['require_numbers'] ) )
		{
			$this->setRequireNumbers( $Settings['require_numbers'] );
		}

		if( isset( $Settings['require_special_chars'] ) )
		{
			$this->setRequireSpecialChars( $Settings['require_special_chars'] );
		}

		return $this;
	}
}
