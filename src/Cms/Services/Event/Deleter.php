<?php

namespace Neuron\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;

/**
 * Event deletion service.
 *
 * @package Neuron\Cms\Services\Event
 */
class Deleter
{
	private IEventRepository $_repository;

	public function __construct( IEventRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Delete an event
	 *
	 * @param Event $event
	 * @return bool
	 */
	public function delete( Event $event ): bool
	{
		return $this->_repository->delete( $event );
	}
}
