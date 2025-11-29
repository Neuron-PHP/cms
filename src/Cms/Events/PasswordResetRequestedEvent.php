<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user requests a password reset.
 *
 * This event is triggered when a password reset token is generated
 * and the reset email is about to be sent.
 *
 * Use cases:
 * - Security monitoring for reset request patterns
 * - Track potential account takeover attempts
 * - Send security alerts for unexpected reset requests
 * - Log password reset activity for compliance
 * - Implement rate limiting on reset requests
 * - Detect and prevent password reset abuse
 *
 * @package Neuron\Cms\Events
 */
class PasswordResetRequestedEvent implements IEvent
{
	/**
	 * @param User $user User who requested password reset
	 * @param string $ip IP address of the request
	 */
	public function __construct(
		public readonly User $user,
		public readonly string $ip
	)
	{
	}

	public function getName(): string
	{
		return 'password.reset_requested';
	}
}
