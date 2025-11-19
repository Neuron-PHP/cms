<?php

namespace Neuron\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;

/**
 * Tag creation service.
 *
 * Creates individual tags with slug generation.
 *
 * @package Neuron\Cms\Services\Tag
 */
class Creator
{
	private ITagRepository $_tagRepository;

	public function __construct( ITagRepository $tagRepository )
	{
		$this->_tagRepository = $tagRepository;
	}

	/**
	 * Create a new tag
	 *
	 * @param string $name Tag name
	 * @param string|null $slug Optional custom slug (auto-generated if not provided)
	 * @return Tag
	 */
	public function create( string $name, ?string $slug = null ): Tag
	{
		$tag = new Tag();
		$tag->setName( $name );
		$tag->setSlug( $slug ?: $this->generateSlug( $name ) );

		return $this->_tagRepository->create( $tag );
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
			$slug = 'tag-' . uniqid();
		}

		return $slug;
	}
}
