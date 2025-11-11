<?php

namespace Neuron\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;

/**
 * Tag resolution service.
 *
 * Resolves comma-separated tag names to Tag objects,
 * creating tags that don't exist.
 *
 * @package Neuron\Cms\Services\Tag
 */
class Resolver
{
	private ITagRepository $_tagRepository;
	private Creator $_tagCreator;

	public function __construct(
		ITagRepository $tagRepository,
		Creator $tagCreator
	)
	{
		$this->_tagRepository = $tagRepository;
		$this->_tagCreator = $tagCreator;
	}

	/**
	 * Resolve comma-separated tag names to Tag objects.
	 * Creates tags that don't exist.
	 *
	 * @param string $tagNames Comma-separated tag names
	 * @return Tag[]
	 */
	public function resolveFromString( string $tagNames ): array
	{
		if( empty( $tagNames ) )
		{
			return [];
		}

		$tags = [];
		$tagArray = array_map( 'trim', explode( ',', $tagNames ) );

		foreach( $tagArray as $tagName )
		{
			if( empty( $tagName ) )
			{
				continue;
			}

			$tag = $this->_tagRepository->findByName( $tagName );
			if( !$tag )
			{
				$tag = $this->_tagCreator->create( $tagName );
			}
			$tags[] = $tag;
		}

		return $tags;
	}
}
