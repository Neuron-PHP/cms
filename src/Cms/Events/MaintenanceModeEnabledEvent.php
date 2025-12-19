<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when maintenance mode is enabled.
 *
 * This event is triggered when an administrator enables maintenance mode,
 * making the site unavailable to regular users.
 *
 * Use cases:
 * - Notify administrators of maintenance mode activation
 * - Update external status pages or monitoring systems
 * - Log maintenance windows for compliance
 * - Send notifications to users about scheduled downtime
 * - Trigger cache clearing or preparation tasks
 * - Update load balancer or CDN configurations
 *
 * @package Neuron\Cms\Events
 */
class MaintenanceModeEnabledEvent implements IEvent
{
	/**
	 * @param string $enabledBy Username or identifier of who enabled maintenance mode
	 * @param string $message Maintenance message to display to users
	 */
	public function __construct(
		public readonly string $enabledBy,
		public readonly string $message
	)
	{
	}

	public function getName(): string
	{
		return 'maintenance.enabled';
	}
}
