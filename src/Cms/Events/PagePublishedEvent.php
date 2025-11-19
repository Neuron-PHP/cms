<?php

namespace Neuron\Cms\Events;

use Neuron\Cms\Models\Page;

/**
 * Event fired when a page is published.
 *
 * @package Neuron\Cms\Events
 */
class PagePublishedEvent
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
