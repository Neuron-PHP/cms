<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Events\CategoryCreatedEvent;
use Neuron\Patterns\Registry;
use DateTimeImmutable;

/**
 * Category creation service.
 *
 * Creates new categories with slug generation.
 *
 * @package Neuron\Cms\Services\Category
 */
class Creator
{
	private ICategoryRepository $_categoryRepository;

	public function __construct( ICategoryRepository $categoryRepository )
	{
		$this->_categoryRepository = $categoryRepository;
	}

	/**
	 * Create a new category
	 *
	 * @param string $name Category name
	 * @param string $slug URL-friendly slug
	 * @param string|null $description Optional description
	 * @return Category
	 * @throws \Exception If category creation fails
	 */
	public function create(
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

		$category = new Category();
		$category->setName( $name );
		$category->setSlug( $slug );
		$category->setDescription( $description );
		$category->setCreatedAt( new DateTimeImmutable() );

		$category = $this->_categoryRepository->create( $category );

		// Emit category created event
		$emitter = Registry::getInstance()->get( 'EventEmitter' );
		if( $emitter )
		{
			$emitter->emit( new CategoryCreatedEvent( $category ) );
		}

		return $category;
	}

	/**
	 * Generate URL-friendly slug from name
	 *
	 * For names with only non-ASCII characters (e.g., "你好", "مرحبا"),
	 * generates a fallback slug using uniqid().
	 *
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		// Fallback for names with no ASCII characters
		if( $slug === '' )
		{
			$slug = 'category-' . uniqid();
		}

		return $slug;
	}
}
