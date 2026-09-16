<?php

namespace Tests\Cms\Services\Menu;

use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Enums\MenuItemType;
use Neuron\Cms\Enums\MenuLocation;
use Neuron\Cms\Models\Menu;
use Neuron\Cms\Models\MenuItem;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IMenuRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Menu\MenuService;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

class MenuServiceTest extends TestCase
{
	private IMenuRepository $menus;
	private IPageRepository $pages;
	private SettingManager $settings;
	private MenuService $service;

	protected function setUp(): void
	{
		parent::setUp();

		$this->menus = $this->createMock( IMenuRepository::class );
		$this->pages = $this->createMock( IPageRepository::class );
		$this->settings = $this->createMock( SettingManager::class );
		$this->service = new MenuService( $this->menus, $this->pages, $this->settings );
	}

	public function testForLocationReturnsEmptyWhenNoMenuExists(): void
	{
		$this->settings->method( 'get' )->willReturn( null );
		$this->menus->method( 'findBySlug' )->willReturn( null );
		$this->menus->method( 'findByLocation' )->willReturn( null );

		$this->assertSame( [], $this->service->forLocation( MenuLocation::HEADER->value ) );
	}

	public function testForLocationPrefersConfiguredSlug(): void
	{
		$menu = new Menu();
		$menu->setId( 7 );
		$menu->setSlug( 'main-nav' );

		$this->settings->method( 'get' )
			->with( 'menus', 'header' )
			->willReturn( 'main-nav' );

		$this->menus->expects( $this->once() )
			->method( 'findBySlug' )
			->with( 'main-nav' )
			->willReturn( $menu );

		$this->menus->expects( $this->never() )->method( 'findByLocation' );
		$this->menus->method( 'getItems' )->with( 7 )->willReturn( [] );

		$this->assertSame( [], $this->service->forLocation( MenuLocation::HEADER->value ) );
	}

	public function testBuildTreeNestsVisibleItemsAndResolvesUrls(): void
	{
		$parent = $this->item( 1, [
			'label' => 'About',
			'item_type' => MenuItemType::URL->value,
			'url' => '/about',
			'sort_order' => 0
		] );
		$child = $this->item( 2, [
			'parent_id' => 1,
			'label' => 'Team',
			'item_type' => MenuItemType::URL->value,
			'url' => '/team',
			'target' => '_blank'
		] );
		$hidden = $this->item( 3, [
			'label' => 'Secret',
			'item_type' => MenuItemType::URL->value,
			'url' => '/secret',
			'is_visible' => false
		] );
		$route = $this->item( 4, [
			'label' => 'Home',
			'item_type' => MenuItemType::ROUTE->value,
			'route_name' => 'home'
		] );

		$this->menus->method( 'getItems' )->with( 1 )->willReturn( [ $parent, $child, $hidden, $route ] );

		$tree = $this->service->buildTree( 1 );

		$this->assertCount( 2, $tree );
		$this->assertSame( 'About', $tree[0]['label'] );
		$this->assertSame( '/about', $tree[0]['href'] );
		$this->assertCount( 1, $tree[0]['children'] );
		$this->assertSame( 'Team', $tree[0]['children'][0]['label'] );
		$this->assertSame( '_blank', $tree[0]['children'][0]['target'] );
		$this->assertSame( 'Home', $tree[1]['label'] );
		$this->assertSame( '/', $tree[1]['href'] );
	}

	public function testBuildTreeResolvesPublishedPageAndSkipsUnpublished(): void
	{
		$pageItem = $this->item( 1, [
			'label' => 'Welcome',
			'item_type' => MenuItemType::PAGE->value,
			'page_id' => 9
		] );
		$draftItem = $this->item( 2, [
			'label' => 'Draft',
			'item_type' => MenuItemType::PAGE->value,
			'page_id' => 10
		] );

		$published = new Page();
		$published->setSlug( 'welcome' );
		$published->setStatus( ContentStatus::PUBLISHED->value );

		$draft = new Page();
		$draft->setSlug( 'draft' );
		$draft->setStatus( ContentStatus::DRAFT->value );

		$this->menus->method( 'getItems' )->willReturn( [ $pageItem, $draftItem ] );
		$this->pages->method( 'findById' )->willReturnMap( [
			[ 9, $published ],
			[ 10, $draft ],
		] );

		$tree = $this->service->buildTree( 1 );

		$this->assertCount( 1, $tree );
		$this->assertSame( 'Welcome', $tree[0]['label'] );
		$this->assertSame( '/pages/welcome', $tree[0]['href'] );
	}

	public function testBuildTreeKeepsParentWhenOnlyChildrenAreValid(): void
	{
		$parent = $this->item( 1, [
			'label' => 'More',
			'item_type' => MenuItemType::PAGE->value,
			'page_id' => null
		] );
		$child = $this->item( 2, [
			'parent_id' => 1,
			'label' => 'Blog',
			'item_type' => MenuItemType::URL->value,
			'url' => '/blog'
		] );

		$this->menus->method( 'getItems' )->willReturn( [ $parent, $child ] );

		$tree = $this->service->buildTree( 1 );

		$this->assertCount( 1, $tree );
		$this->assertSame( '#', $tree[0]['href'] );
		$this->assertSame( 'Blog', $tree[0]['children'][0]['label'] );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function item( int $id, array $overrides = [] ): MenuItem
	{
		$item = new MenuItem();
		$item->setId( $id );
		$item->setMenuId( 1 );
		$item->setLabel( $overrides['label'] ?? 'Item' );
		$item->setItemType( $overrides['item_type'] ?? MenuItemType::URL->value );
		$item->setParentId( $overrides['parent_id'] ?? null );
		$item->setPageId( $overrides['page_id'] ?? null );
		$item->setRouteName( $overrides['route_name'] ?? null );
		$item->setUrl( $overrides['url'] ?? null );
		$item->setTarget( $overrides['target'] ?? '_self' );
		$item->setIsVisible( $overrides['is_visible'] ?? true );

		return $item;
	}
}
