<?php

namespace Neuron\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\SlugGenerator;

/**
 * Tag creation service.
 *
 * Creates individual tags with slug generation.
 *
 * @package Neuron\Cms\Services\Tag
 */
class Creator implements ITagCreator
{
	private ITagRepository $_tagRepository;
	private SlugGenerator $_slugGenerator;

	public function __construct( ITagRepository $tagRepository, ?SlugGenerator $slugGenerator = null )
	{
		$this->_tagRepository = $tagRepository;
		$this->_slugGenerator = $slugGenerator ?? new SlugGenerator();
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
	 * generates a fallback slug using a unique identifier.
	 *
	 * @param string $name
	 * @return string
	 */
	private function generateSlug( string $name ): string
	{
		return $this->_slugGenerator->generate( $name, 'tag' );
	}
}
