<?php

namespace Neuron\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Dto\Dto;

/**
 * Post updater service interface
 *
 * @package Neuron\Cms\Services\Post
 */
interface IPostUpdater
{
	/**
	 * Update an existing post from DTO
	 *
	 * @param Dto $request DTO containing post data
	 * @return Post
	 */
	public function update( Dto $request ): Post;
}
