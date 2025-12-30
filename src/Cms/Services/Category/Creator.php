<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryCreatedEvent;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Events\Emitter;
use Neuron\Dto\Dto;
use DateTimeImmutable;

/**
 * Category creation service.
 *
 * Creates new categories with slug generation.
 *
 * @package Neuron\Cms\Services\Category
 */
class Creator implements ICategoryCreator
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
	 * Create a new category from DTO
	 *
	 * @param Dto $request DTO containing name, slug, description
	 * @return Category
	 * @throws \Exception If category creation fails
	 */
	public function create( Dto $request ): Category
	{
		// Extract values from DTO
		$name = $request->name;
		$slug = $request->slug ?? '';
		$description = $request->description ?? null;

		// Auto-generate slug from name if empty
		if( empty( $slug ) )
		{
			$slug = $this->generateSlug( $name );
		}

		$category = new Category();
		$category->setName( $name );
		$category->setSlug( $slug );
		$category->setDescription( $description );
		$category->setCreatedAt( new DateTimeImmutable() );

		$category = $this->_categoryRepository->create( $category );

		// Emit category created event
		if( $this->_eventEmitter )
		{
			$this->_eventEmitter->emit( new CategoryCreatedEvent( $category ) );
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
