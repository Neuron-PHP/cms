<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Page;

/**
 * Event fired when a page is updated.
 *
 * @package Neuron\Cms\Events
 */
class PageUpdatedEvent
{
	private Page $_page;

	public function __construct( Page $page )
	{
		$this->_page = $page;
	}

	public function getPage(): Page
	{
		return $this->_page;
	}
}
