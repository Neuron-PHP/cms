<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Category;
use Neuron\Events\IEvent;

/**
 * Event fired when a category is updated.
 *
 * @package Neuron\Cms\Events
 */
class CategoryUpdatedEvent implements IEvent
{
	public function __construct( public readonly Category $category )
	{
	}

	public function getName(): string
	{
		return 'category.updated';
	}
}
