<?php

namespace Neuron\Cms\Features;

use Neuron\Application\CrossCutting\Event;
use Neuron\Log\Log;
use Neuron\Mvc\Events\Http404;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;

/**
 * Global route filter that answers 404 for routes belonging to a CMS area
 * switched off under `features` in neuron.yaml.
 *
 * The routes stay registered, so route_path() still resolves them; only
 * dispatch is refused. mvc does not turn exceptions thrown by filters into
 * a 404, so the page is rendered here and returned as the response.
 *
 * @package Neuron\Cms\Features
 */
class FeatureFilter extends Filter
{
	private Features $_features;

	/** @var \Closure( array<string, mixed> ): mixed */
	private \Closure $_notFound;

	/**
	 * @param Features $features
	 * @param \Closure $notFound Renders the 404 page from route parameters,
	 *        normally Application::executeController()
	 */
	public function __construct( Features $features, \Closure $notFound )
	{
		$this->_features = $features;
		$this->_notFound = $notFound;

		parent::__construct(
			function( RouteMap $route ) {
				return $this->check( $route );
			},
			null
		);
	}

	/**
	 * @param RouteMap $route
	 * @return mixed|null The 404 page, or null to let the route run
	 */
	private function check( RouteMap $route ): mixed
	{
		if( $this->_features->allowsRoute( $route ) )
		{
			return null;
		}

		$path = $route->getPath();

		Log::debug( "Route {$path} belongs to a disabled CMS feature" );

		Event::emit( new Http404( $path ) );

		return ( $this->_notFound )(
			array_merge(
				$route->Parameters,
				[
					'route' => $path,
					'Controller' => 'Neuron\Mvc\Controllers\HttpCodes@code404',
				]
			)
		);
	}
}
