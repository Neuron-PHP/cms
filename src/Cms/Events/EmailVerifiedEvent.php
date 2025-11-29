<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user successfully verifies their email address.
 *
 * This event is triggered when a user clicks the verification link in
 * their email and their email address is confirmed.
 *
 * Use cases:
 * - Send welcome email after verification
 * - Trigger onboarding workflows
 * - Grant additional permissions or access
 * - Track email verification rates and timing
 * - Enable email notifications for the user
 * - Update user status and analytics
 *
 * @package Neuron\Cms\Events
 */
class EmailVerifiedEvent implements IEvent
{
	/**
	 * @param User $user User whose email was verified
	 */
	public function __construct(
		public readonly User $user
	)
	{
	}

	public function getName(): string
	{
		return 'email.verified';
	}
}
