<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\User;
use Neuron\Events\IEvent;

/**
 * Event fired when a new user is created.
 *
 * @package Neuron\Cms\Events
 */
class UserCreatedEvent implements IEvent
{
	public function __construct( public readonly User $user )
	{
	}

	public function getName(): string
	{
		return 'user.created';
	}
}
