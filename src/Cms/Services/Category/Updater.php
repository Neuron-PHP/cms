<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryUpdatedEvent;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Events\Emitter;
use Neuron\Dto\Dto;
use DateTimeImmutable;

/**
 * Category update service.
 *
 * Updates existing categories.
 *
 * @package Neuron\Cms\Services\Category
 */
class Updater implements ICategoryUpdater
{
	private ICategoryRepository $_categoryRepository;
	private SlugGenerator $_slugGenerator;
	private ?Emitter $_eventEmitter;

	public function __construct(
		ICategoryRepository $categoryRepository,
		?SlugGenerator $slugGenerator = null,
		?Emitter $eventEmitter = null
	)
	{
		$this->_categoryRepository = $categoryRepository;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
		$this->_eventEmitter = $eventEmitter;
	}

	/**
	 * Update an existing category from DTO
	 *
	 * @param Dto $request DTO containing id, name, slug, description
	 * @return Category
	 * @throws \Exception If category not found or update fails
	 */
	public function update( Dto $request ): Category
	{
		// Extract values from DTO
		$id = $request->id;
		$name = $request->name;
		$slug = $request->slug ?? '';
		$description = $request->description ?? null;

		// Look up the category
		$category = $this->_categoryRepository->findById( $id );
		if( !$category )
		{
			throw new \Exception( "Category with ID {$id} not found" );
		}

		// Auto-generate slug from name if empty
		if( empty( $slug ) )
		{
			$slug = $this->generateSlug( $name );
		}

		$category->setName( $name );
		$category->setSlug( $slug );
		$category->setDescription( $description );
		$category->setUpdatedAt( new DateTimeImmutable() );

		$this->_categoryRepository->update( $category );

		// Emit category updated event
		if( $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new CategoryUpdatedEvent( $category ) );
		}

		return $category;
	}

	/**
	 * Generate URL-friendly slug from name
	 *
	 * For names with only non-ASCII characters (e.g., "你好", "مرحبا"),
	 * generates a fallback slug using a unique identifier.
	 *
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		return $this->_slugGenerator->generate( $name, 'category' );
	}
}
