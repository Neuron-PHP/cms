<?php

namespace Neuron\Cms\Tests\Auth;

use Neuron\Cms\Auth\ResendVerificationThrottle;
use Neuron\Routing\RateLimit\Storage\MemoryRateLimitStorage;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ResendVerificationThrottle.
 *
 * @package Neuron\Cms\Tests\Auth
 */
class ResendVerificationThrottleTest extends TestCase
{
	private ResendVerificationThrottle $_throttle;
	private MemoryRateLimitStorage $_storage;

	protected function setUp(): void
	{
		// Use in-memory storage for testing
		$this->_storage = new MemoryRateLimitStorage();
		$this->_throttle = new ResendVerificationThrottle( $this->_storage );
	}

	protected function tearDown(): void
	{
		$this->_storage->clear();
	}

	/**
	 * Test that first request is allowed.
	 */
	public function testFirstRequestIsAllowed(): void
	{
		$allowed = $this->_throttle->allow( '192.168.1.1', 'test@example.com' );
		$this->assertTrue( $allowed, 'First request should be allowed' );
	}

	/**
	 * Test that multiple requests from same IP are allowed up to limit.
	 */
	public function testMultipleIpRequestsUpToLimit(): void
	{
		$ip = '192.168.1.1';

		// First 5 requests should be allowed (using different emails)
		for( $i = 1; $i <= 5; $i++ )
		{
			$allowed = $this->_throttle->allow( $ip, "test{$i}@example.com" );
			$this->assertTrue( $allowed, "Request {$i} should be allowed" );
		}

		// 6th request should be blocked (exceeds IP limit of 5)
		$allowed = $this->_throttle->allow( $ip, 'test6@example.com' );
		$this->assertFalse( $allowed, '6th request from same IP should be blocked' );
	}

	/**
	 * Test that multiple requests for same email are blocked.
	 */
	public function testMultipleEmailRequestsBlocked(): void
	{
		$email = 'test@example.com';

		// First request should be allowed
		$allowed = $this->_throttle->allow( '192.168.1.1', $email );
		$this->assertTrue( $allowed, 'First request should be allowed' );

		// Second request for same email should be blocked (even from different IP)
		$allowed = $this->_throttle->allow( '192.168.1.2', $email );
		$this->assertFalse( $allowed, 'Second request for same email should be blocked' );
	}

	/**
	 * Test that email throttling is case-insensitive and trims whitespace.
	 */
	public function testEmailNormalization(): void
	{
		$email1 = 'test@example.com';
		$email2 = 'TEST@EXAMPLE.COM';
		$email3 = ' test@example.com ';

		// First request
		$allowed = $this->_throttle->allow( '192.168.1.1', $email1 );
		$this->assertTrue( $allowed, 'First request should be allowed' );

		// Second request with uppercase email should be blocked
		$allowed = $this->_throttle->allow( '192.168.1.2', $email2 );
		$this->assertFalse( $allowed, 'Uppercase email should be treated as same email' );

		// Third request with whitespace should be blocked
		$allowed = $this->_throttle->allow( '192.168.1.3', $email3 );
		$this->assertFalse( $allowed, 'Email with whitespace should be treated as same email' );
	}

	/**
	 * Test that different IPs can make requests for different emails.
	 */
	public function testDifferentIpsDifferentEmails(): void
	{
		// Multiple IPs with different emails should all be allowed
		for( $i = 1; $i <= 5; $i++ )
		{
			$allowed = $this->_throttle->allow( "192.168.1.{$i}", "test{$i}@example.com" );
			$this->assertTrue( $allowed, "Request from IP {$i} should be allowed" );
		}
	}

	/**
	 * Test getting remaining IP attempts.
	 */
	public function testGetRemainingIpAttempts(): void
	{
		$ip = '192.168.1.1';

		// Initially should have 5 attempts
		$remaining = $this->_throttle->getRemainingIpAttempts( $ip );
		$this->assertEquals( 5, $remaining, 'Should have 5 remaining attempts initially' );

		// After one request, should have 4 remaining
		$this->_throttle->allow( $ip, 'test1@example.com' );
		$remaining = $this->_throttle->getRemainingIpAttempts( $ip );
		$this->assertEquals( 4, $remaining, 'Should have 4 remaining attempts after one request' );

		// After 5 requests, should have 0 remaining
		for( $i = 2; $i <= 5; $i++ )
		{
			$this->_throttle->allow( $ip, "test{$i}@example.com" );
		}
		$remaining = $this->_throttle->getRemainingIpAttempts( $ip );
		$this->assertEquals( 0, $remaining, 'Should have 0 remaining attempts after 5 requests' );
	}

	/**
	 * Test getting remaining email attempts.
	 */
	public function testGetRemainingEmailAttempts(): void
	{
		$email = 'test@example.com';

		// Initially should have 1 attempt
		$remaining = $this->_throttle->getRemainingEmailAttempts( $email );
		$this->assertEquals( 1, $remaining, 'Should have 1 remaining attempt initially' );

		// After one request, should have 0 remaining
		$this->_throttle->allow( '192.168.1.1', $email );
		$remaining = $this->_throttle->getRemainingEmailAttempts( $email );
		$this->assertEquals( 0, $remaining, 'Should have 0 remaining attempts after one request' );
	}

	/**
	 * Test resetting IP limit.
	 */
	public function testResetIp(): void
	{
		$ip = '192.168.1.1';

		// Make 5 requests to exhaust limit
		for( $i = 1; $i <= 5; $i++ )
		{
			$this->_throttle->allow( $ip, "test{$i}@example.com" );
		}

		// Should be blocked
		$allowed = $this->_throttle->allow( $ip, 'test6@example.com' );
		$this->assertFalse( $allowed, 'Should be blocked after 5 requests' );

		// Reset IP limit
		$this->_throttle->resetIp( $ip );

		// Should be allowed again
		$allowed = $this->_throttle->allow( $ip, 'test7@example.com' );
		$this->assertTrue( $allowed, 'Should be allowed after reset' );
	}

	/**
	 * Test resetting email limit.
	 */
	public function testResetEmail(): void
	{
		$email = 'test@example.com';

		// Make first request
		$this->_throttle->allow( '192.168.1.1', $email );

		// Should be blocked
		$allowed = $this->_throttle->allow( '192.168.1.2', $email );
		$this->assertFalse( $allowed, 'Should be blocked for same email' );

		// Reset email limit
		$this->_throttle->resetEmail( $email );

		// Should be allowed again
		$allowed = $this->_throttle->allow( '192.168.1.3', $email );
		$this->assertTrue( $allowed, 'Should be allowed after reset' );
	}

	/**
	 * Test custom configuration.
	 */
	public function testCustomConfiguration(): void
	{
		// Create throttle with custom limits
		$throttle = new ResendVerificationThrottle( $this->_storage, [
			'ip_limit' => 2,
			'email_limit' => 3
		] );

		$ip = '192.168.1.1';

		// Should allow 2 IP requests
		$this->assertTrue( $throttle->allow( $ip, 'test1@example.com' ) );
		$this->assertTrue( $throttle->allow( $ip, 'test2@example.com' ) );

		// 3rd IP request should be blocked
		$this->assertFalse( $throttle->allow( $ip, 'test3@example.com' ) );
	}

	/**
	 * Test that combined check works correctly.
	 */
	public function testCombinedIpAndEmailCheck(): void
	{
		$ip = '192.168.1.1';
		$email = 'test@example.com';

		// First request should pass both checks
		$allowed = $this->_throttle->allow( $ip, $email );
		$this->assertTrue( $allowed, 'First request should be allowed' );

		// Second request for same email from different IP should fail email check
		$allowed = $this->_throttle->allow( '192.168.1.2', $email );
		$this->assertFalse( $allowed, 'Should fail email check even with different IP' );

		// Request for different email from same IP should pass (IP has attempts left)
		$allowed = $this->_throttle->allow( $ip, 'other@example.com' );
		$this->assertTrue( $allowed, 'Should pass with different email, same IP still has attempts' );
	}

	/**
	 * Test clearing all limits.
	 */
	public function testClear(): void
	{
		// Make some requests
		$this->_throttle->allow( '192.168.1.1', 'test1@example.com' );
		$this->_throttle->allow( '192.168.1.1', 'test2@example.com' );

		// Clear all
		$this->_throttle->clear();

		// Should have full attempts again
		$remaining = $this->_throttle->getRemainingIpAttempts( '192.168.1.1' );
		$this->assertEquals( 5, $remaining, 'Should have full attempts after clear' );
	}
}
