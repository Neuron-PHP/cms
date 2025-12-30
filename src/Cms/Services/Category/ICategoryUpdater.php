<?php

namespace Neuron\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Dto\Dto;

/**
 * Category updater service interface
 *
 * @package Neuron\Cms\Services\Category
 */
interface ICategoryUpdater
{
	/**
	 * Update an existing category from DTO
	 *
	 * @param Dto $request DTO containing category data
	 * @return Category
	 */
	public function update( Dto $request ): Category;
}
