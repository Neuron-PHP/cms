<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Dto\Dto;

/**
 * Page updater service interface
 *
 * @package Neuron\Cms\Services\Page
 */
interface IPageUpdater
{
	/**
	 * Update an existing page from DTO
	 *
	 * @param Dto $request DTO containing page data
	 * @return Page
	 */
	public function update( Dto $request ): Page;
}
