<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a user is deleted.
 *
 * @package Neuron\Cms\Events
 */
class UserDeletedEvent implements IEvent
{
	public function __construct( public readonly int $userId )
	{
	}

	public function getName(): string
	{
		return 'user.deleted';
	}
}
