<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Dto\Dto;

/**
 * Event category creation service.
 *
 * @package Neuron\Cms\Services\EventCategory
 */
class Creator implements IEventCategoryCreator
{
	private IEventCategoryRepository $_repository;
	private SlugGenerator $_slugGenerator;

	public function __construct( IEventCategoryRepository $repository, ?SlugGenerator $slugGenerator = null )
	{
		$this->_repository = $repository;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
	}

	/**
	 * Create a new event category from DTO
	 *
	 * @param Dto $request DTO containing category data
	 * @return EventCategory
	 * @throws \RuntimeException if slug already exists
	 */
	public function create( Dto $request ): EventCategory
	{
		// Extract values from DTO
		$name = $request->name;
		$slug = $request->slug ?? '';
		$color = $request->color ?? '#3b82f6';
		$description = $request->description ?? null;

		$category = new EventCategory();
		$category->setName( $name );
		$category->setSlug( $slug ?: $this->generateSlug( $name ) );
		$category->setColor( $color );
		$category->setDescription( $description );

		// Check for duplicate slug
		if( $this->_repository->slugExists( $category->getSlug() ) )
		{
			throw new \RuntimeException( 'A category with this slug already exists' );
		}

		return $this->_repository->create( $category );
	}

	/**
	 * Generate URL-friendly slug from name
	 *
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		return $this->_slugGenerator->generate( $name, 'category' );
	}
}
