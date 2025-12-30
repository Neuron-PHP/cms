<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Dto\Dto;

/**
 * Post creation service interface
 *
 * @package Neuron\Cms\Services\Post
 */
interface IPostCreator
{
	/**
	 * Create a new post from DTO
	 *
	 * @param Dto $request DTO containing post data
	 * @return Post
	 */
	public function create( Dto $request ): Post;
}
