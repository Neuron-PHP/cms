<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user logs out.
 *
 * This event is triggered when a user explicitly logs out or when
 * their session is terminated.
 *
 * Use cases:
 * - Track user session duration and activity patterns
 * - Clean up user-specific resources or temporary data
 * - Log session end for security audits
 * - Update user activity status (online/offline)
 * - Trigger session analytics and reporting
 * - Send logout confirmation notifications
 *
 * @package Neuron\Cms\Events
 */
class UserLogoutEvent implements IEvent
{
	/**
	 * @param User $user User who logged out
	 * @param float $sessionDuration Session duration in seconds
	 */
	public function __construct(
		public readonly User $user,
		public readonly float $sessionDuration
	)
	{
	}

	public function getName(): string
	{
		return 'user.logout';
	}
}
