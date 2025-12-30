<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Dto;

/**
 * Event category update service.
 *
 * @package Neuron\Cms\Services\EventCategory
 */
class Updater implements IEventCategoryUpdater
{
	private IEventCategoryRepository $_repository;

	public function __construct( IEventCategoryRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Update an event category from DTO
	 *
	 * @param Dto $request DTO containing id and category data
	 * @return EventCategory
	 * @throws \RuntimeException if category not found or slug already exists
	 */
	public function update( Dto $request ): EventCategory
	{
		// Extract values from DTO
		$id = $request->id;
		$name = $request->name;
		$slug = $request->slug;
		$color = $request->color;
		$description = $request->description ?? null;

		// Look up the category
		$category = $this->_repository->findById( $id );
		if( !$category )
		{
			throw new \RuntimeException( "Category with ID {$id} not found" );
		}

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
