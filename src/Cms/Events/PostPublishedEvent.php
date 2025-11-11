<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Post;
use Neuron\Events\IEvent;

/**
 * Event fired when a post is published.
 *
 * @package Neuron\Cms\Events
 */
class PostPublishedEvent implements IEvent
{
	public function __construct( public readonly Post $post )
	{
	}

	public function getName(): string
	{
		return 'post.published';
	}
}
