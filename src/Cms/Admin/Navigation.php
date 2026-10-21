<?php

namespace Neuron\Cms\Admin;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\Components\ComponentLoader;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;

/**
 * Admin navigation entries, merged from the CMS's own pages and whatever
 * installed web components declare through INavigationProvider.
 *
 * Templates ask for a group ('system', ...) and get a sorted list of
 * [label, route, icon, order, provider]. Items whose named route is not
 * registered on the running application are dropped, so a component that
 * is installed but disabled never leaves a dead link behind.
 *
 * @package Neuron\Cms\Admin
 */
class Navigation
{
	public const GROUP_SYSTEM = 'system';

	/**
	 * Pages the CMS contributes itself, keyed by group.
	 *
	 * @var array<string, list<array{label:string,route:string,icon?:string,order?:int}>>
	 */
	private const BUILT_IN = [
		self::GROUP_SYSTEM => [
			[ 'label' => 'Scheduled Jobs', 'route' => 'admin_jobs', 'icon' => 'bi-clock-history', 'order' => 10 ],
		],
	];

	/**
	 * Navigation items for one group, CMS entries first within equal order,
	 * then components, sorted by order then label.
	 *
	 * @param string $group
	 * @return list<array{label:string,route:string,icon:?string,order:int,provider:?string}>
	 */
	public static function items( string $group ): array
	{
		$items = [];

		foreach( self::BUILT_IN[ $group ] ?? [] as $item )
		{
			$items[] = [
				'label'    => $item['label'],
				'route'    => $item['route'],
				'icon'     => $item['icon'] ?? null,
				'order'    => $item['order'] ?? 100,
				'provider' => null,
			];
		}

		foreach( ComponentLoader::navigation( $group ) as $item )
		{
			$items[] = [
				'label'    => $item['label'],
				'route'    => $item['route'],
				'icon'     => $item['icon'],
				'order'    => $item['order'],
				'provider' => $item['provider'],
			];
		}

		$items = array_values( array_filter( $items, fn( array $item ) => self::routeExists( $item['route'] ) ) );

		usort( $items, fn( array $a, array $b ) => [ $a['order'], $a['label'] ] <=> [ $b['order'], $b['label'] ] );

		return $items;
	}

	/**
	 * Whether the running application has this named route. When no
	 * application is in the registry (unit tests, CLI) nothing can be
	 * verified, so the item is kept.
	 *
	 * @param string $route
	 * @return bool
	 */
	private static function routeExists( string $route ): bool
	{
		$app = Registry::getInstance()->get( RegistryKeys::APP );

		if( !$app instanceof IMvcApplication )
		{
			return true;
		}

		try
		{
			return $app->getRouter()->getRouteByName( $route ) !== null;
		}
		catch( \Throwable )
		{
			return false;
		}
	}
}
