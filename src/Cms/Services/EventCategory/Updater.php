<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;

/**
 * Event category update service.
 *
 * @package Neuron\Cms\Services\EventCategory
 */
class Updater
{
	private IEventCategoryRepository $_repository;

	public function __construct( IEventCategoryRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Update an event category
	 *
	 * @param EventCategory $category
	 * @param string $name
	 * @param string $slug
	 * @param string $color
	 * @param string|null $description
	 * @return EventCategory
	 * @throws \RuntimeException if slug already exists for another category
	 */
	public function update(
		EventCategory $category,
		string $name,
		string $slug,
		string $color,
		?string $description = null
	): EventCategory
	{
		// Check for duplicate slug (excluding current category)
		if( $this->_repository->slugExists( $slug, $category->getId() ) )
		{
			throw new \RuntimeException( 'A category with this slug already exists' );
		}

		$category->setName( $name );
		$category->setSlug( $slug );
		$category->setColor( $color );
		$category->setDescription( $description );

		return $this->_repository->update( $category );
	}
}
