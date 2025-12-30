<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Dto\Dto;

/**
 * Event creation service interface
 *
 * @package Neuron\Cms\Services\Event
 */
interface IEventCreator
{
	/**
	 * Create a new event from DTO
	 *
	 * @param Dto $request DTO containing event data
	 * @return Event
	 */
	public function create( Dto $request ): Event;
}
