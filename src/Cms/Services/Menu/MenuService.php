<?php

namespace Neuron\Cms\Services\Menu;

use Neuron\Cms\Enums\MenuItemType;
use Neuron\Cms\Models\MenuItem;
use Neuron\Cms\Repositories\IMenuRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Data\Settings\SettingManager;

/**
 * Builds nested navigation trees for public layouts.
 *
 * @package Neuron\Cms\Services\Menu
 */
class MenuService
{
	/** @var array<string, string> */
	public const ROUTES = [
		'home' => 'Home',
		'blog' => 'Blog',
		'calendar' => 'Calendar',
		'contact' => 'Contact',
	];

	private IMenuRepository $_menus;
	private IPageRepository $_pages;
	private ?SettingManager $_settings;

	public function __construct(
		IMenuRepository $menus,
		IPageRepository $pages,
		?SettingManager $settings = null
	)
	{
		$this->_menus = $menus;
		$this->_pages = $pages;
		$this->_settings = $settings;
	}

	/**
	 * Nested nav items for a published location (header or footer).
	 *
	 * @return array<int, array{label: string, href: string, target: string, children: array}>
	 */
	public function forLocation( string $location ): array
	{
		$slug = $this->_settings?->get( 'menus', $location );

		$menu = is_string( $slug ) && $slug !== ''
			? $this->_menus->findBySlug( $slug )
			: null;

		$menu ??= $this->_menus->findByLocation( $location );

		if( !$menu )
		{
			return [];
		}

		return $this->buildTree( $menu->getId() );
	}

	/**
	 * @return array<int, array{label: string, href: string, target: string, children: array}>
	 */
	public function buildTree( int $menuId ): array
	{
		$items = $this->_menus->getItems( $menuId );
		$byParent = [];

		foreach( $items as $item )
		{
			if( !$item->isVisible() )
			{
				continue;
			}

			$parentKey = $item->getParentId() ?? 0;
			$byParent[ $parentKey ][] = $item;
		}

		return $this->mapBranch( $byParent[0] ?? [], $byParent );
	}

	/**
	 * @param MenuItem[] $items
	 * @param array<int, MenuItem[]> $byParent
	 * @return array<int, array{label: string, href: string, target: string, children: array}>
	 */
	private function mapBranch( array $items, array $byParent ): array
	{
		$branch = [];

		foreach( $items as $item )
		{
			$id = $item->getId() ?? 0;
			$children = $this->mapBranch( $byParent[ $id ] ?? [], $byParent );
			$href = $this->resolveHref( $item );

			if( $href === '' && $children === [] )
			{
				continue;
			}

			$branch[] = [
				'label' => $item->getLabel(),
				'href' => $href !== '' ? $href : '#',
				'target' => $item->getTarget(),
				'children' => $children,
			];
		}

		return $branch;
	}

	private function resolveHref( MenuItem $item ): string
	{
		return match( $item->getItemTypeMode() )
		{
			MenuItemType::PAGE => $this->pageHref( $item->getPageId() ),
			MenuItemType::ROUTE => $this->routeHref( $item->getRouteName() ),
			MenuItemType::URL => trim( (string) $item->getUrl() ),
		};
	}

	private function pageHref( ?int $pageId ): string
	{
		if( !$pageId )
		{
			return '';
		}

		$page = $this->_pages->findById( $pageId );

		if( !$page || !$page->isPublished() )
		{
			return '';
		}

		$path = function_exists( 'route_path' )
			? route_path( 'page', [ 'slug' => $page->getSlug() ] )
			: '';

		return $path !== '' ? $path : '/pages/' . $page->getSlug();
	}

	private function routeHref( ?string $routeName ): string
	{
		if( !$routeName )
		{
			return '';
		}

		$path = function_exists( 'route_path' ) ? route_path( $routeName ) : '';

		return $path !== '' ? $path : ( $routeName === 'home' ? '/' : '' );
	}
}
