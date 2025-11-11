<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a category is deleted.
 *
 * @package Neuron\Cms\Events
 */
class CategoryDeletedEvent implements IEvent
{
	public function __construct( public readonly int $categoryId )
	{
	}

	public function getName(): string
	{
		return 'category.deleted';
	}
}
