<?php

namespace Neuron\Cms\Services\Page;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;

/**
 * Page deletion service.
 *
 * Handles page deletion.
 *
 * @package Neuron\Cms\Services\Page
 */
class Deleter
{
	private IPageRepository $_pageRepository;

	public function __construct( IPageRepository $pageRepository )
	{
		$this->_pageRepository = $pageRepository;
	}

	/**
	 * Delete a page
	 *
	 * @param Page $page Page to delete
	 * @return bool True if deleted successfully
	 */
	public function delete( Page $page ): bool
	{
		if( !$page->getId() )
		{
			return false;
		}

		return $this->_pageRepository->delete( $page->getId() );
	}
}
