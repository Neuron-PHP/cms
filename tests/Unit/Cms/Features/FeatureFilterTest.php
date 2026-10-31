<?php

namespace Tests\Cms\Features;

use Neuron\Cms\Controllers\Calendar;
use Neuron\Cms\Controllers\Pages;
use Neuron\Cms\Features\FeatureFilter;
use Neuron\Cms\Features\Features;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Routing\RouteMap;
use PHPUnit\Framework\TestCase;

class FeatureFilterTest extends TestCase
{
	/** @var list<array<string, mixed>> */
	private array $_rendered = [];

	private function filter(): FeatureFilter
	{
		$features = new Features( new SettingManager( new Memory( [ 'features' => [ 'calendar' => false ] ] ) ) );

		return new FeatureFilter(
			$features,
			function( array $parameters ) {
				$this->_rendered[] = $parameters;
				return '404 page';
			}
		);
	}

	private function route( string $path, string $controller ): RouteMap
	{
		$route = new RouteMap( $path, fn() => 'ran' );
		$route->Payload = [ 'Controller' => $controller ];
		$route->Parameters = [ 'slug' => 'camp', 'Controller' => $controller ];

		return $route;
	}

	public function testAllowedRouteContinues(): void
	{
		$this->assertNull( $this->filter()->pre( $this->route( '/pages/:slug', Pages::class . '@show' ) ) );
		$this->assertSame( [], $this->_rendered );
	}

	public function testDisabledRouteRendersTheNotFoundPage(): void
	{
		$result = $this->filter()->pre( $this->route( '/calendar/event/:slug', Calendar::class . '@show' ) );

		$this->assertSame( '404 page', $result );
		$this->assertCount( 1, $this->_rendered );
		$this->assertSame( 'Neuron\Mvc\Controllers\HttpCodes@code404', $this->_rendered[0]['Controller'] );
		$this->assertSame( '/calendar/event/:slug', $this->_rendered[0]['route'] );
		$this->assertSame( 'camp', $this->_rendered[0]['slug'] );
	}
}
