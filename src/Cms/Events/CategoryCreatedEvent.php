<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Category;
use Neuron\Events\IEvent;

/**
 * Event fired when a new category is created.
 *
 * @package Neuron\Cms\Events
 */
class CategoryCreatedEvent implements IEvent
{
	public function __construct( public readonly Category $category )
	{
	}

	public function getName(): string
	{
		return 'category.created';
	}
}
