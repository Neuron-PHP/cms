<?php

namespace Neuron\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;

/**
 * Tag creation service interface
 *
 * @package Neuron\Cms\Services\Tag
 */
interface ITagCreator
{
	/**
	 * Create a new tag
	 *
	 * @param string $name Tag name
	 * @param string|null $slug Optional custom slug
	 * @return Tag
	 */
	public function create( string $name, ?string $slug = null ): Tag;
}
