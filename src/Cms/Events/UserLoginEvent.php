<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user successfully logs in.
 *
 * This event is triggered after successful authentication when a user
 * logs in to the system, either as an admin or member.
 *
 * Use cases:
 * - Track user login activity for security audits
 * - Send login notifications to users
 * - Update last login timestamp
 * - Detect unusual login patterns (location, time)
 * - Generate analytics on user engagement
 * - Trigger two-factor authentication workflows
 *
 * @package Neuron\Cms\Events
 */
class UserLoginEvent implements IEvent
{
	/**
	 * @param User $user User who logged in
	 * @param string $ip IP address of the login
	 * @param float $timestamp Login timestamp (microtime)
	 */
	public function __construct(
		public readonly User $user,
		public readonly string $ip,
		public readonly float $timestamp
	)
	{
	}

	public function getName(): string
	{
		return 'user.login';
	}
}
