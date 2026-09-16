<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Enums\MenuItemType;
use Neuron\Cms\Enums\MenuLocation;
use Neuron\Cms\Models\Menu;
use Neuron\Cms\Models\MenuItem;
use Neuron\Cms\Repositories\DatabaseMenuRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseMenuRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseMenuRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE menus (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				location VARCHAR(32) NOT NULL DEFAULT 'header',
				description TEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$this->pdo->exec( "
			CREATE TABLE menu_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				menu_id INTEGER NOT NULL,
				parent_id INTEGER,
				label VARCHAR(255) NOT NULL,
				item_type VARCHAR(32) NOT NULL DEFAULT 'url',
				page_id INTEGER,
				route_name VARCHAR(128),
				url VARCHAR(512),
				target VARCHAR(16) NOT NULL DEFAULT '_self',
				sort_order INTEGER NOT NULL DEFAULT 0,
				is_visible INTEGER NOT NULL DEFAULT 1,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name' => ':memory:'
			] );

		$this->repository = new DatabaseMenuRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function makeMenu( string $name = 'Header', string $slug = 'header', string $location = MenuLocation::HEADER->value ): Menu
	{
		$menu = new Menu();
		$menu->setName( $name );
		$menu->setSlug( $slug );
		$menu->setLocation( $location );
		$menu->setDescription( 'Primary nav' );

		return $this->repository->create( $menu );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function makeItem( int $menuId, array $overrides = [] ): MenuItem
	{
		$item = new MenuItem();
		$item->setMenuId( $menuId );
		$item->setLabel( $overrides['label'] ?? 'Home' );
		$item->setItemType( $overrides['item_type'] ?? MenuItemType::URL->value );
		$item->setParentId( $overrides['parent_id'] ?? null );
		$item->setPageId( $overrides['page_id'] ?? null );
		$item->setRouteName( $overrides['route_name'] ?? null );
		$item->setUrl( $overrides['url'] ?? '/' );
		$item->setTarget( $overrides['target'] ?? '_self' );
		$item->setSortOrder( $overrides['sort_order'] ?? 0 );
		$item->setIsVisible( $overrides['is_visible'] ?? true );

		return $this->repository->createItem( $item );
	}

	public function testAllReturnsEmptyArrayWhenNoMenus(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$menu = $this->makeMenu();

		$found = $this->repository->findById( $menu->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Header', $found->getName() );
		$this->assertSame( 'header', $found->getSlug() );
		$this->assertSame( MenuLocation::HEADER->value, $found->getLocation() );
		$this->assertSame( 'Primary nav', $found->getDescription() );
	}

	public function testFindBySlugAndLocation(): void
	{
		$this->makeMenu( 'Footer', 'footer', MenuLocation::FOOTER->value );

		$bySlug = $this->repository->findBySlug( 'footer' );
		$byLocation = $this->repository->findByLocation( MenuLocation::FOOTER->value );

		$this->assertNotNull( $bySlug );
		$this->assertSame( MenuLocation::FOOTER->value, $bySlug->getLocation() );
		$this->assertNotNull( $byLocation );
		$this->assertSame( 'footer', $byLocation->getSlug() );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testAllReturnsMenusSortedByName(): void
	{
		$this->makeMenu( 'Footer', 'footer', MenuLocation::FOOTER->value );
		$this->makeMenu( 'Header', 'header' );

		$menus = $this->repository->all();

		$this->assertCount( 2, $menus );
		$this->assertSame( 'Footer', $menus[0]->getName() );
		$this->assertSame( 'Header', $menus[1]->getName() );
	}

	public function testUpdateChangesFields(): void
	{
		$menu = $this->makeMenu();
		$menu->setName( 'Main' );
		$menu->setLocation( MenuLocation::FOOTER->value );
		$menu->setDescription( null );

		$this->repository->update( $menu );

		$found = $this->repository->findById( $menu->getId() );
		$this->assertSame( 'Main', $found->getName() );
		$this->assertSame( MenuLocation::FOOTER->value, $found->getLocation() );
		$this->assertNull( $found->getDescription() );
	}

	public function testSlugExists(): void
	{
		$menu = $this->makeMenu();

		$this->assertTrue( $this->repository->slugExists( 'header' ) );
		$this->assertFalse( $this->repository->slugExists( 'header', $menu->getId() ) );
		$this->assertFalse( $this->repository->slugExists( 'missing' ) );
	}

	public function testDeleteRemovesMenuAndItems(): void
	{
		$menu = $this->makeMenu();
		$this->makeItem( $menu->getId() );

		$this->assertTrue( $this->repository->delete( $menu ) );
		$this->assertNull( $this->repository->findById( $menu->getId() ) );
		$this->assertSame( 0, $this->repository->countItems( $menu->getId() ) );
	}

	public function testCreateAndFindItem(): void
	{
		$menu = $this->makeMenu();
		$item = $this->makeItem( $menu->getId(), [
			'label' => 'About',
			'item_type' => MenuItemType::PAGE->value,
			'page_id' => 4,
			'sort_order' => 3,
			'target' => '_blank'
		] );

		$found = $this->repository->findItemById( $item->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'About', $found->getLabel() );
		$this->assertSame( MenuItemType::PAGE->value, $found->getItemType() );
		$this->assertSame( 4, $found->getPageId() );
		$this->assertSame( 3, $found->getSortOrder() );
		$this->assertSame( '_blank', $found->getTarget() );
	}

	public function testGetItemsOrdersBySortThenId(): void
	{
		$menu = $this->makeMenu();
		$this->makeItem( $menu->getId(), [ 'label' => 'Third', 'sort_order' => 2 ] );
		$first = $this->makeItem( $menu->getId(), [ 'label' => 'First', 'sort_order' => 1 ] );
		$second = $this->makeItem( $menu->getId(), [ 'label' => 'Second', 'sort_order' => 1 ] );

		$items = $this->repository->getItems( $menu->getId() );

		$this->assertCount( 3, $items );
		$this->assertSame( 'First', $items[0]->getLabel() );
		$this->assertSame( 'Second', $items[1]->getLabel() );
		$this->assertSame( 'Third', $items[2]->getLabel() );
		$this->assertSame( $first->getId(), $items[0]->getId() );
		$this->assertSame( $second->getId(), $items[1]->getId() );
	}

	public function testUpdateItemChangesFields(): void
	{
		$menu = $this->makeMenu();
		$item = $this->makeItem( $menu->getId() );
		$item->setLabel( 'Updated' );
		$item->setUrl( null );
		$item->setIsVisible( false );

		$this->repository->updateItem( $item );

		$found = $this->repository->findItemById( $item->getId() );
		$this->assertSame( 'Updated', $found->getLabel() );
		$this->assertNull( $found->getUrl() );
		$this->assertFalse( $found->isVisible() );
	}

	public function testDeleteItemPromotesChildren(): void
	{
		$menu = $this->makeMenu();
		$parent = $this->makeItem( $menu->getId(), [ 'label' => 'Parent' ] );
		$child = $this->makeItem( $menu->getId(), [ 'label' => 'Child', 'parent_id' => $parent->getId() ] );

		$this->assertTrue( $this->repository->deleteItem( $parent ) );
		$this->assertNull( $this->repository->findItemById( $parent->getId() ) );

		$found = $this->repository->findItemById( $child->getId() );
		$this->assertNotNull( $found );
		$this->assertNull( $found->getParentId() );
	}

	public function testCountItemsAndNextSortOrder(): void
	{
		$menu = $this->makeMenu();

		$this->assertSame( 0, $this->repository->countItems( $menu->getId() ) );
		$this->assertSame( 0, $this->repository->nextItemSortOrder( $menu->getId() ) );

		$this->makeItem( $menu->getId(), [ 'sort_order' => 0 ] );
		$this->makeItem( $menu->getId(), [ 'sort_order' => 4 ] );

		$this->assertSame( 2, $this->repository->countItems( $menu->getId() ) );
		$this->assertSame( 5, $this->repository->nextItemSortOrder( $menu->getId() ) );
	}

	public function testNextItemSortOrderScopesToParent(): void
	{
		$menu = $this->makeMenu();
		$parent = $this->makeItem( $menu->getId(), [ 'label' => 'Parent', 'sort_order' => 9 ] );
		$this->makeItem( $menu->getId(), [ 'label' => 'Child', 'parent_id' => $parent->getId(), 'sort_order' => 1 ] );

		$this->assertSame( 10, $this->repository->nextItemSortOrder( $menu->getId() ) );
		$this->assertSame( 2, $this->repository->nextItemSortOrder( $menu->getId(), $parent->getId() ) );
	}

	public function testFindItemByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findItemById( 999 ) );
	}

	public function testInvalidLocationFallsBackToHeader(): void
	{
		$menu = $this->makeMenu( 'Odd', 'odd', 'not-a-location' );

		$this->assertSame( MenuLocation::HEADER->value, $menu->getLocation() );
	}
}
