<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a login attempt fails.
 *
 * This event is triggered when authentication fails due to invalid
 * credentials, locked account, or other authentication errors.
 *
 * Use cases:
 * - Security monitoring and brute force detection
 * - Automatically lock accounts after failed attempts
 * - Send security alerts for repeated failures
 * - Track potential security threats and attack patterns
 * - Generate security reports for compliance
 * - Implement progressive delays on failed attempts
 *
 * @package Neuron\Cms\Events
 */
class UserLoginFailedEvent implements IEvent
{
	/**
	 * @param string $identifier Username or email used in failed attempt
	 * @param string $ip IP address of the failed login
	 * @param float $timestamp Failure timestamp (microtime)
	 * @param string $reason Reason for failure (invalid_credentials, account_locked, etc.)
	 */
	public function __construct(
		public readonly string $identifier,
		public readonly string $ip,
		public readonly float $timestamp,
		public readonly string $reason
	)
	{
	}

	public function getName(): string
	{
		return 'user.login_failed';
	}
}
