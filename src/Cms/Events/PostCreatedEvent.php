<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Post;
use Neuron\Events\IEvent;

/**
 * Event fired when a new post is created.
 *
 * @package Neuron\Cms\Events
 */
class PostCreatedEvent implements IEvent
{
	public function __construct( public readonly Post $post )
	{
	}

	public function getName(): string
	{
		return 'post.created';
	}
}
