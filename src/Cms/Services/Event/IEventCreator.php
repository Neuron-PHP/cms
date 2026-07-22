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

	/**
	 * Duplicate an existing event as a new draft.
	 *
	 * @param Event $source Event to copy
	 * @param int $createdBy User id for the new event
	 * @return Event
	 */
	public function duplicate( Event $source, int $createdBy ): Event;
}
