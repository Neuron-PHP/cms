<?php

namespace Neuron\Cms\Services\EventCategory;

use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;

/**
 * Event category creation service.
 *
 * @package Neuron\Cms\Services\EventCategory
 */
class Creator
{
	private IEventCategoryRepository $_repository;

	public function __construct( IEventCategoryRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Create a new event category
	 *
	 * @param string $name Category name
	 * @param string|null $slug Optional custom slug (auto-generated if not provided)
	 * @param string $color Hex color code
	 * @param string|null $description Optional description
	 * @return EventCategory
	 * @throws \RuntimeException if slug already exists
	 */
	public function create(
		string $name,
		?string $slug = null,
		string $color = '#3b82f6',
		?string $description = null
	): EventCategory
	{
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
