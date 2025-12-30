<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Dto\Dto;

/**
 * Category creation service interface
 *
 * @package Neuron\Cms\Services\Category
 */
interface ICategoryCreator
{
	/**
	 * Create a new category from DTO
	 *
	 * @param Dto $request DTO containing category data
	 * @return Category
	 */
	public function create( Dto $request ): Category;
}
