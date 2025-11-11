<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a user is updated.
 *
 * @package Neuron\Cms\Events
 */
class UserUpdatedEvent implements IEvent
{
	public function __construct( public readonly User $user )
	{
	}

	public function getName(): string
	{
		return 'user.updated';
	}
}
