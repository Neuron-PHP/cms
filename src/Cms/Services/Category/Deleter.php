<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryDeletedEvent;
use Neuron\Patterns\Registry;

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

	public function __construct( ICategoryRepository $categoryRepository )
	{
		$this->_categoryRepository = $categoryRepository;
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
		if( $result )
		{
			$emitter = Registry::getInstance()->get( 'EventEmitter' );
			if( $emitter )
			{
				$emitter->emit( new CategoryDeletedEvent( $categoryId ) );
			}
		}

		return $result;
	}
}
