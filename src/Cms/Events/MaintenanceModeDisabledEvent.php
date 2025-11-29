<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when maintenance mode is disabled.
 *
 * This event is triggered when an administrator disables maintenance mode,
 * making the site available to all users again.
 *
 * Use cases:
 * - Notify administrators that site is back online
 * - Update external status pages or monitoring systems
 * - Log maintenance completion for compliance
 * - Send notifications that site is available
 * - Trigger cache warming or verification tasks
 * - Update load balancer or CDN configurations
 *
 * @package Neuron\Cms\Events
 */
class MaintenanceModeDisabledEvent implements IEvent
{
	/**
	 * @param string $disabledBy Username or identifier of who disabled maintenance mode
	 */
	public function __construct(
		public readonly string $disabledBy
	)
	{
	}

	public function getName(): string
	{
		return 'maintenance.disabled';
	}
}
