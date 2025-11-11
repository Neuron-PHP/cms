<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryUpdatedEvent;
use Neuron\Patterns\Registry;
use DateTimeImmutable;

/**
 * Category update service.
 *
 * Updates existing categories.
 *
 * @package Neuron\Cms\Services\Category
 */
class Updater
{
	private ICategoryRepository $_categoryRepository;

	public function __construct( ICategoryRepository $categoryRepository )
	{
		$this->_categoryRepository = $categoryRepository;
	}

	/**
	 * Update an existing category
	 *
	 * @param Category $category The category to update
	 * @param string $name Category name
	 * @param string $slug URL-friendly slug
	 * @param string|null $description Optional description
	 * @return Category
	 * @throws \Exception If category update fails
	 */
	public function update(
		Category $category,
		string $name,
		string $slug,
		?string $description = null
	): Category
	{
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
		$emitter = Registry::getInstance()->get( 'EventEmitter' );
		if( $emitter )
		{
			$emitter->emit( new CategoryUpdatedEvent( $category ) );
		}

		return $category;
	}

	/**
	 * Generate URL-friendly slug from name
	 *
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		return trim( $slug, '-' );
	}
}
