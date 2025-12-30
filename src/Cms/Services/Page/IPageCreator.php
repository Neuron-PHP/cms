<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Dto\Dto;

/**
 * Page creation service interface
 *
 * @package Neuron\Cms\Services\Page
 */
interface IPageCreator
{
	/**
	 * Create a new page from DTO
	 *
	 * @param Dto $request DTO containing page data
	 * @return Page
	 */
	public function create( Dto $request ): Page;
}
