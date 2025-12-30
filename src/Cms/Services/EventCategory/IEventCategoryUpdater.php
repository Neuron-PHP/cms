<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Dto\Dto;

/**
 * Event category update service interface
 *
 * @package Neuron\Cms\Services\EventCategory
 */
interface IEventCategoryUpdater
{
	/**
	 * Update an event category from DTO
	 *
	 * @param Dto $request DTO containing id and category data
	 * @return EventCategory
	 * @throws \RuntimeException if category not found or slug already exists
	 */
	public function update( Dto $request ): EventCategory;
}
