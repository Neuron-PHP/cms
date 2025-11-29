<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user successfully completes a password reset.
 *
 * This event is triggered after a user uses a valid reset token
 * and successfully changes their password.
 *
 * Use cases:
 * - Send confirmation email of password change
 * - Invalidate all existing sessions for security
 * - Log password change for security audits
 * - Track password reset completion rates
 * - Alert user of successful password change
 * - Update security compliance records
 *
 * @package Neuron\Cms\Events
 */
class PasswordResetCompletedEvent implements IEvent
{
	/**
	 * @param User $user User who completed password reset
	 * @param string $ip IP address where reset was completed
	 */
	public function __construct(
		public readonly User $user,
		public readonly string $ip
	)
	{
	}

	public function getName(): string
	{
		return 'password.reset_completed';
	}
}
