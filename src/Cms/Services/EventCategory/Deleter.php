<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;

/**
 * Event category deletion service.
 *
 * @package Neuron\Cms\Services\EventCategory
 */
class Deleter
{
	private IEventCategoryRepository $_repository;

	public function __construct( IEventCategoryRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Delete an event category
	 *
	 * @param EventCategory $category
	 * @return bool
	 */
	public function delete( EventCategory $category ): bool
	{
		return $this->_repository->delete( $category );
	}
}
