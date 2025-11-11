<?php

namespace Neuron\Cms\Listeners;

use Neuron\Events\IListener;
use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Cms\Events\UserUpdatedEvent;
use Neuron\Cms\Events\UserDeletedEvent;
use Neuron\Log\Log;

/**
 * Logs user activity for audit trail purposes.
 *
 * @package Neuron\Cms\Listeners
 */
class LogUserActivityListener implements IListener
{
	/**
	 * Handle user events
	 *
	 * @param UserCreatedEvent|UserUpdatedEvent|UserDeletedEvent $event
	 * @return void
	 */
	public function event( $event ): void
	{
		if( $event instanceof UserCreatedEvent )
		{
			Log::info( "User created: {$event->user->getUsername()} (ID: {$event->user->getId()})" );
		}
		elseif( $event instanceof UserUpdatedEvent )
		{
			Log::info( "User updated: {$event->user->getUsername()} (ID: {$event->user->getId()})" );
		}
		elseif( $event instanceof UserDeletedEvent )
		{
			Log::info( "User deleted: ID {$event->userId}" );
		}
	}
}
