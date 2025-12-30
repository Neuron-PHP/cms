<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Dto\Dto;

/**
 * Event updater service interface
 *
 * @package Neuron\Cms\Services\Event
 */
interface IEventUpdater
{
	/**
	 * Update an existing event from DTO
	 *
	 * @param Dto $request DTO containing event data
	 * @return Event
	 */
	public function update( Dto $request ): Event;
}
