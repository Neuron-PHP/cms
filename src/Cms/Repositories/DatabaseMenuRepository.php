<?php

namespace Neuron\Cms\Repositories;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Database\ConnectionFactory;
use Neuron\Cms\Models\Menu;
use Neuron\Cms\Models\MenuItem;
use Neuron\Data\Settings\SettingManager;
use PDO;

/**
 * Database-backed menu repository.
 *
 * @package Neuron\Cms\Repositories
 */
class DatabaseMenuRepository implements IMenuRepository
{
	private PDO $_pdo;

	/**
	 * @throws Exception if database configuration is missing or adapter is unsupported
	 */
	public function __construct( SettingManager $settings )
	{
		$this->_pdo = ConnectionFactory::createFromSettings( $settings );

		Menu::setPdo( $this->_pdo );
		MenuItem::setPdo( $this->_pdo );
	}

	public function all(): array
	{
		$stmt = $this->_pdo->query( 'SELECT * FROM menus ORDER BY name ASC' );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => Menu::fromArray( $row ), $rows );
	}

	public function findById( int $id ): ?Menu
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM menus WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Menu::fromArray( $row ) : null;
	}

	public function findBySlug( string $slug ): ?Menu
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM menus WHERE slug = ? LIMIT 1' );
		$stmt->execute( [ $slug ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Menu::fromArray( $row ) : null;
	}

	public function findByLocation( string $location ): ?Menu
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM menus WHERE location = ? ORDER BY name ASC LIMIT 1' );
		$stmt->execute( [ $location ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? Menu::fromArray( $row ) : null;
	}

	public function create( Menu $menu ): Menu
	{
		$now = new DateTimeImmutable();
		$menu->setCreatedAt( $now );
		$menu->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO menus ( name, slug, location, description, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$menu->getName(),
			$menu->getSlug(),
			$menu->getLocation(),
			$menu->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$menu->setId( (int) $this->_pdo->lastInsertId() );

		return $menu;
	}

	public function update( Menu $menu ): Menu
	{
		$now = new DateTimeImmutable();
		$menu->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE menus
			SET name = ?, slug = ?, location = ?, description = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$menu->getName(),
			$menu->getSlug(),
			$menu->getLocation(),
			$menu->getDescription(),
			$now->format( 'Y-m-d H:i:s' ),
			$menu->getId()
		] );

		return $menu;
	}

	public function delete( Menu $menu ): bool
	{
		$stmt = $this->_pdo->prepare( 'DELETE FROM menu_items WHERE menu_id = ?' );
		$stmt->execute( [ $menu->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM menus WHERE id = ?' );

		return $stmt->execute( [ $menu->getId() ] );
	}

	public function slugExists( string $slug, ?int $excludeId = null ): bool
	{
		if( $excludeId )
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM menus WHERE slug = ? AND id != ?' );
			$stmt->execute( [ $slug, $excludeId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM menus WHERE slug = ?' );
			$stmt->execute( [ $slug ] );
		}

		return (int) $stmt->fetchColumn() > 0;
	}

	public function getItems( int $menuId ): array
	{
		$stmt = $this->_pdo->prepare(
			'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC'
		);
		$stmt->execute( [ $menuId ] );
		$rows = $stmt->fetchAll( PDO::FETCH_ASSOC ) ?: [];

		return array_map( fn( array $row ) => MenuItem::fromArray( $row ), $rows );
	}

	public function findItemById( int $id ): ?MenuItem
	{
		$stmt = $this->_pdo->prepare( 'SELECT * FROM menu_items WHERE id = ?' );
		$stmt->execute( [ $id ] );
		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		return $row ? MenuItem::fromArray( $row ) : null;
	}

	public function createItem( MenuItem $item ): MenuItem
	{
		$now = new DateTimeImmutable();
		$item->setCreatedAt( $now );
		$item->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'INSERT INTO menu_items
			( menu_id, parent_id, label, item_type, page_id, route_name, url, target, sort_order, is_visible, created_at, updated_at )
			VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )'
		);

		$stmt->execute( [
			$item->getMenuId(),
			$item->getParentId(),
			$item->getLabel(),
			$item->getItemType(),
			$item->getPageId(),
			$item->getRouteName(),
			$item->getUrl(),
			$item->getTarget(),
			$item->getSortOrder(),
			$item->isVisible() ? 1 : 0,
			$now->format( 'Y-m-d H:i:s' ),
			$now->format( 'Y-m-d H:i:s' )
		] );

		$item->setId( (int) $this->_pdo->lastInsertId() );

		return $item;
	}

	public function updateItem( MenuItem $item ): MenuItem
	{
		$now = new DateTimeImmutable();
		$item->setUpdatedAt( $now );

		$stmt = $this->_pdo->prepare(
			'UPDATE menu_items
			SET parent_id = ?, label = ?, item_type = ?, page_id = ?, route_name = ?, url = ?, target = ?, sort_order = ?, is_visible = ?, updated_at = ?
			WHERE id = ?'
		);

		$stmt->execute( [
			$item->getParentId(),
			$item->getLabel(),
			$item->getItemType(),
			$item->getPageId(),
			$item->getRouteName(),
			$item->getUrl(),
			$item->getTarget(),
			$item->getSortOrder(),
			$item->isVisible() ? 1 : 0,
			$now->format( 'Y-m-d H:i:s' ),
			$item->getId()
		] );

		return $item;
	}

	public function deleteItem( MenuItem $item ): bool
	{
		$stmt = $this->_pdo->prepare( 'UPDATE menu_items SET parent_id = NULL WHERE parent_id = ?' );
		$stmt->execute( [ $item->getId() ] );

		$stmt = $this->_pdo->prepare( 'DELETE FROM menu_items WHERE id = ?' );

		return $stmt->execute( [ $item->getId() ] );
	}

	public function countItems( int $menuId ): int
	{
		$stmt = $this->_pdo->prepare( 'SELECT COUNT(*) FROM menu_items WHERE menu_id = ?' );
		$stmt->execute( [ $menuId ] );

		return (int) $stmt->fetchColumn();
	}

	public function nextItemSortOrder( int $menuId, ?int $parentId = null ): int
	{
		if( $parentId )
		{
			$stmt = $this->_pdo->prepare(
				'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM menu_items WHERE menu_id = ? AND parent_id = ?'
			);
			$stmt->execute( [ $menuId, $parentId ] );
		}
		else
		{
			$stmt = $this->_pdo->prepare(
				'SELECT COALESCE( MAX( sort_order ), -1 ) + 1 FROM menu_items WHERE menu_id = ? AND parent_id IS NULL'
			);
			$stmt->execute( [ $menuId ] );
		}

		return (int) $stmt->fetchColumn();
	}
}
