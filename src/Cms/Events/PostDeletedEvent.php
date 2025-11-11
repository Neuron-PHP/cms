<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a post is deleted.
 *
 * @package Neuron\Cms\Events
 */
class PostDeletedEvent implements IEvent
{
	public function __construct( public readonly int $postId )
	{
	}

	public function getName(): string
	{
		return 'post.deleted';
	}
}
