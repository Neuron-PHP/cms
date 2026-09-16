<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Menu;
use Neuron\Cms\Models\MenuItem;

/**
 * Repository interface for named menus and their items.
 *
 * @package Neuron\Cms\Repositories
 */
interface IMenuRepository
{
	/**
	 * @return Menu[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Menu;

	public function findBySlug( string $slug ): ?Menu;

	public function findByLocation( string $location ): ?Menu;

	public function create( Menu $menu ): Menu;

	public function update( Menu $menu ): Menu;

	public function delete( Menu $menu ): bool;

	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * @return MenuItem[]
	 */
	public function getItems( int $menuId ): array;

	public function findItemById( int $id ): ?MenuItem;

	public function createItem( MenuItem $item ): MenuItem;

	public function updateItem( MenuItem $item ): MenuItem;

	public function deleteItem( MenuItem $item ): bool;

	public function countItems( int $menuId ): int;

	public function nextItemSortOrder( int $menuId, ?int $parentId = null ): int;
}
