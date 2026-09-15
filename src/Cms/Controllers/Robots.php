<?php

namespace Neuron\Cms\Controllers;

use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Get;

/**
 * robots.txt pointing crawlers at the XML sitemap.
 *
 * @package Neuron\Cms\Controllers
 */
class Robots extends Content
{
	#[Get('/robots.txt', name: 'robots')]
	public function index( Request $request ): never
	{
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo $this->document();
		exit;
	}

	public function document(): string
	{
		$sitemap = $this->absoluteRoute( 'sitemap' );
		$body = "User-agent: *\nAllow: /\n";

		if( $sitemap !== '' )
		{
			$body .= 'Sitemap: ' . $sitemap . "\n";
		}

		return $body;
	}
}
