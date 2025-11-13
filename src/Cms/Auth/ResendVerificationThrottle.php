<?php

namespace Neuron\Cms\Auth;

use Neuron\Routing\RateLimit\RateLimitConfig;
use Neuron\Routing\RateLimit\Storage\IRateLimitStorage;
use Neuron\Routing\RateLimit\Storage\RateLimitStorageFactory;

/**
 * Rate limiting for resend verification email requests.
 *
 * Implements combined IP and email-based throttling to prevent DOS/spam:
 * - Per-IP limit: 5 requests per 5 minutes
 * - Per-email limit: 1 request per 5 minutes
 *
 * @package Neuron\Cms\Auth
 */
class ResendVerificationThrottle
{
	private IRateLimitStorage $_storage;

	// Default limits
	private int $_ipLimit = 5;
	private int $_ipWindow = 300; // 5 minutes
	private int $_emailLimit = 1;
	private int $_emailWindow = 300; // 5 minutes

	/**
	 * @param IRateLimitStorage|null $storage Storage backend (defaults to file-based)
	 * @param array $config Optional configuration overrides
	 */
	public function __construct( ?IRateLimitStorage $storage = null, array $config = [] )
	{
		if( $storage === null )
		{
			// Use file-based storage by default
			$rateLimitConfig = new RateLimitConfig([
				'storage' => 'file',
				'file_path' => sys_get_temp_dir() . '/neuron/rate_limits/resend_verification',
				'key_prefix' => 'resend_verify_'
			]);

			$storage = RateLimitStorageFactory::create( $rateLimitConfig );
		}

		$this->_storage = $storage;

		// Allow configuration overrides
		if( isset( $config['ip_limit'] ) )
		{
			$this->_ipLimit = (int) $config['ip_limit'];
		}
		if( isset( $config['ip_window'] ) )
		{
			$this->_ipWindow = (int) $config['ip_window'];
		}
		if( isset( $config['email_limit'] ) )
		{
			$this->_emailLimit = (int) $config['email_limit'];
		}
		if( isset( $config['email_window'] ) )
		{
			$this->_emailWindow = (int) $config['email_window'];
		}
	}

	/**
	 * Check if resend verification is allowed for given IP and email.
	 *
	 * Checks both IP-based and email-based rate limits. Both must pass
	 * for the request to be allowed.
	 *
	 * @param string $ip Client IP address
	 * @param string $email Email address (will be hashed)
	 * @return bool True if allowed, false if rate limited
	 */
	public function allow( string $ip, string $email ): bool
	{
		// Check IP-based limit
		$ipKey = 'ip:' . $ip;
		if( !$this->_storage->allow( $ipKey, $this->_ipLimit, $this->_ipWindow ) )
		{
			return false;
		}

		// Check email-based limit (use hash to prevent storing plain emails)
		$emailKey = 'email:' . hash( 'sha256', strtolower( trim( $email ) ) );
		if( !$this->_storage->allow( $emailKey, $this->_emailLimit, $this->_emailWindow ) )
		{
			// If email limit exceeded, we need to rollback the IP increment
			// However, the storage interface doesn't support transactions
			// So we accept this minor inconsistency for simplicity
			return false;
		}

		return true;
	}

	/**
	 * Get remaining attempts for an IP address.
	 *
	 * @param string $ip Client IP address
	 * @return int Number of remaining attempts
	 */
	public function getRemainingIpAttempts( string $ip ): int
	{
		$ipKey = 'ip:' . $ip;
		return $this->_storage->getRemainingAttempts( $ipKey, $this->_ipLimit, $this->_ipWindow );
	}

	/**
	 * Get remaining attempts for an email address.
	 *
	 * @param string $email Email address
	 * @return int Number of remaining attempts
	 */
	public function getRemainingEmailAttempts( string $email ): int
	{
		$emailKey = 'email:' . hash( 'sha256', strtolower( trim( $email ) ) );
		return $this->_storage->getRemainingAttempts( $emailKey, $this->_emailLimit, $this->_emailWindow );
	}

	/**
	 * Get reset time for IP-based limit.
	 *
	 * @param string $ip Client IP address
	 * @return int Unix timestamp when limit resets
	 */
	public function getIpResetTime( string $ip ): int
	{
		$ipKey = 'ip:' . $ip;
		return $this->_storage->getResetTime( $ipKey, $this->_ipWindow );
	}

	/**
	 * Get reset time for email-based limit.
	 *
	 * @param string $email Email address
	 * @return int Unix timestamp when limit resets
	 */
	public function getEmailResetTime( string $email ): int
	{
		$emailKey = 'email:' . hash( 'sha256', strtolower( trim( $email ) ) );
		return $this->_storage->getResetTime( $emailKey, $this->_emailWindow );
	}

	/**
	 * Reset rate limit for a specific IP address.
	 *
	 * Useful for testing or administrative override.
	 *
	 * @param string $ip Client IP address
	 * @return void
	 */
	public function resetIp( string $ip ): void
	{
		$ipKey = 'ip:' . $ip;
		$this->_storage->reset( $ipKey );
	}

	/**
	 * Reset rate limit for a specific email address.
	 *
	 * Useful for testing or administrative override.
	 *
	 * @param string $email Email address
	 * @return void
	 */
	public function resetEmail( string $email ): void
	{
		$emailKey = 'email:' . hash( 'sha256', strtolower( trim( $email ) ) );
		$this->_storage->reset( $emailKey );
	}

	/**
	 * Clear all rate limit data.
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->_storage->clear();
	}

	/**
	 * Get the storage backend.
	 *
	 * @return IRateLimitStorage
	 */
	public function getStorage(): IRateLimitStorage
	{
		return $this->_storage;
	}
}
