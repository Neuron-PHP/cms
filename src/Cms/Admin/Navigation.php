<?php

namespace Neuron\Cms\Admin;

use Neuron\Cms\Features\Features;
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
	public const GROUP_CONTENT = 'content';
	public const GROUP_SYSTEM = 'system';

	/**
	 * Pages the CMS contributes itself, keyed by group. `feature` ties an
	 * entry to an optional area (see Features); `section` groups entries
	 * between dividers.
	 *
	 * @var array<string, list<array{label:string,route:string,icon?:string,order?:int,section?:string,feature?:string}>>
	 */
	private const BUILT_IN = [
		self::GROUP_CONTENT => [
			[ 'label' => 'Posts', 'route' => 'admin_posts', 'icon' => 'bi-file-text', 'order' => 10, 'section' => 'content', 'feature' => Features::BLOG ],
			[ 'label' => 'Pages', 'route' => 'admin_pages', 'icon' => 'bi-file-earmark', 'order' => 20, 'section' => 'content' ],
			[ 'label' => 'Media', 'route' => 'admin_media', 'icon' => 'bi-images', 'order' => 30, 'section' => 'content' ],
			[ 'label' => 'Categories', 'route' => 'admin_categories', 'icon' => 'bi-folder', 'order' => 40, 'section' => 'taxonomy', 'feature' => Features::BLOG ],
			[ 'label' => 'Tags', 'route' => 'admin_tags', 'icon' => 'bi-tags', 'order' => 41, 'section' => 'taxonomy', 'feature' => Features::BLOG ],
			[ 'label' => 'Events', 'route' => 'admin_events', 'icon' => 'bi-calendar-event', 'order' => 50, 'section' => 'events', 'feature' => Features::CALENDAR ],
			[ 'label' => 'Event Categories', 'route' => 'admin_event_categories', 'icon' => 'bi-calendar3', 'order' => 51, 'section' => 'events', 'feature' => Features::CALENDAR ],
			[ 'label' => 'Event Registrations', 'route' => 'admin_event_registrations', 'icon' => 'bi-person-check', 'order' => 52, 'section' => 'events', 'feature' => Features::CALENDAR ],
			[ 'label' => 'Contact Submissions', 'route' => 'admin_contact_submissions', 'icon' => 'bi-envelope', 'order' => 60, 'section' => 'inbox', 'feature' => Features::CONTACT ],
			[ 'label' => 'Payments', 'route' => 'admin_payments', 'icon' => 'bi-cash-coin', 'order' => 61, 'section' => 'inbox', 'feature' => Features::PAYMENTS ],
			[ 'label' => 'Subscriptions', 'route' => 'admin_subscriptions', 'icon' => 'bi-arrow-repeat', 'order' => 62, 'section' => 'inbox', 'feature' => Features::PAYMENTS ],
			[ 'label' => 'Products', 'route' => 'admin_products', 'icon' => 'bi-box-seam', 'order' => 70, 'section' => 'store', 'feature' => Features::STORE ],
			[ 'label' => 'Orders', 'route' => 'admin_orders', 'icon' => 'bi-bag-check', 'order' => 71, 'section' => 'store', 'feature' => Features::STORE ],
			[ 'label' => 'Teams', 'route' => 'admin_teams', 'icon' => 'bi-people', 'order' => 80, 'section' => 'site', 'feature' => Features::TEAMS ],
			[ 'label' => 'Carousels', 'route' => 'admin_carousels', 'icon' => 'bi-images', 'order' => 81, 'section' => 'site', 'feature' => Features::CAROUSELS ],
			[ 'label' => 'Testimonials', 'route' => 'admin_testimonials', 'icon' => 'bi-chat-quote', 'order' => 82, 'section' => 'site', 'feature' => Features::TESTIMONIALS ],
			[ 'label' => 'FAQs', 'route' => 'admin_faqs', 'icon' => 'bi-question-circle', 'order' => 83, 'section' => 'site', 'feature' => Features::FAQS ],
			[ 'label' => 'Menus', 'route' => 'admin_menus', 'icon' => 'bi-list', 'order' => 84, 'section' => 'site' ],
			[ 'label' => 'Redirects', 'route' => 'admin_redirects', 'icon' => 'bi-signpost-split', 'order' => 85, 'section' => 'site' ],
		],
		self::GROUP_SYSTEM => [
			[ 'label' => 'Scheduled Jobs', 'route' => 'admin_jobs', 'icon' => 'bi-clock-history', 'order' => 10 ],
		],
	];

	/**
	 * Navigation items for one group, CMS entries first within equal order,
	 * then components, sorted by order then label. Entries for a CMS area
	 * switched off under `features` are left out.
	 *
	 * @param string $group
	 * @return list<array{label:string,route:string,icon:?string,order:int,section:?string,provider:?string}>
	 */
	public static function items( string $group ): array
	{
		$items = [];
		$features = Features::current();

		foreach( self::BUILT_IN[ $group ] ?? [] as $item )
		{
			if( isset( $item['feature'] ) && !$features->isEnabled( $item['feature'] ) )
			{
				continue;
			}

			$items[] = [
				'label'    => $item['label'],
				'route'    => $item['route'],
				'icon'     => $item['icon'] ?? null,
				'order'    => $item['order'] ?? 100,
				'section'  => $item['section'] ?? null,
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
				'section'  => 'components',
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
