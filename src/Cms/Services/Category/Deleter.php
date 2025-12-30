<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryDeletedEvent;
use Neuron\Events\Emitter;

/**
 * Category deletion service.
 *
 * Handles safe deletion of categories.
 *
 * @package Neuron\Cms\Services\Category
 */
class Deleter
{
	private ICategoryRepository $_categoryRepository;
	private ?Emitter $_eventEmitter;

	public function __construct(
		ICategoryRepository $categoryRepository,
		?Emitter $eventEmitter = null
	)
	{
		$this->_categoryRepository = $categoryRepository;
		$this->_eventEmitter = $eventEmitter;
	}

	/**
	 * Delete a category by ID
	 *
	 * @param int $categoryId Category ID to delete
	 * @return bool True if deletion was successful
	 * @throws \Exception If category cannot be deleted
	 */
	public function delete( int $categoryId ): bool
	{
		$category = $this->_categoryRepository->findById( $categoryId );

		if( !$category )
		{
			throw new \Exception( 'Category not found' );
		}

		$result = $this->_categoryRepository->delete( $categoryId );

		// Emit category deleted event
		if( $result && $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new CategoryDeletedEvent( $categoryId ) );
		}

		return $result;
	}
}
