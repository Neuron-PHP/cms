<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Dto\Dto;

/**
 * Event category creation service interface
 *
 * @package Neuron\Cms\Services\EventCategory
 */
interface IEventCategoryCreator
{
	/**
	 * Create a new event category from DTO
	 *
	 * @param Dto $request DTO containing category data
	 * @return EventCategory
	 * @throws \RuntimeException if slug already exists
	 */
	public function create( Dto $request ): EventCategory;
}
