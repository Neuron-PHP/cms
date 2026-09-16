<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Enums\MenuItemType;
use Neuron\Cms\Enums\MenuLocation;
use Neuron\Cms\Models\Menu;
use Neuron\Cms\Models\MenuItem;
use Neuron\Cms\Repositories\IMenuRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Menu\MenuService;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin named-menu management.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Menus extends Content
{
	private IMenuRepository $_repository;
	private IPageRepository $_pages;
	private SlugGenerator $_slugs;

	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		IMenuRepository $repository,
		IPageRepository $pages,
		?SlugGenerator $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_pages      = $pages;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	#[Get('/menus', name: 'admin_menus')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();
		$menus   = $this->_repository->all();
		$counts  = [];

		foreach( $menus as $menu )
		{
			$counts[ $menu->getId() ] = $this->_repository->countItems( $menu->getId() );
		}

		return $this->view()
			->title( 'Menus | Admin' )
			->description( 'Manage navigation menus' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'menus'  => $menus,
				'counts' => $counts,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	#[Get('/menus/create', name: 'admin_menus_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Menu | Admin' )
			->description( 'Add a menu' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'menu'      => null,
				'locations' => MenuLocation::cases()
			] )
			->render( 'create', 'admin' );
	}

	#[Post('/menus', name: 'admin_menus_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_menus_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$menu = new Menu();
		$menu->setName( $name );
		$menu->setSlug( $this->uniqueSlug( $name ) );
		$menu->setLocation( (string) ( $request->post( 'location', MenuLocation::HEADER->value ) ?? MenuLocation::HEADER->value ) );
		$menu->setDescription( $this->optional( $request, 'description' ) );

		try
		{
			$this->_repository->create( $menu );
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::SUCCESS->value, 'Menu created. Add items below.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_menus_create', [], [ FlashMessageType::ERROR->value, 'Failed to create menu: ' . $e->getMessage() ] );
		}
	}

	#[Get('/menus/:id/edit', name: 'admin_menus_edit')]
	public function edit( Request $request ): string
	{
		$menu = $this->requireMenu( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit Menu | Admin' )
			->description( 'Edit a menu' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'menu'      => $menu,
				'items'     => $this->_repository->getItems( $menu->getId() ),
				'locations' => MenuLocation::cases(),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	#[Put('/menus/:id', name: 'admin_menus_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$menu = $this->requireMenu( $request );
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$originalName = $menu->getName();
		$slugInput    = trim( (string) ( $request->post( 'slug', '' ) ?? '' ) );

		$menu->setName( $name );
		$menu->setDescription( $this->optional( $request, 'description' ) );
		$menu->setLocation( (string) ( $request->post( 'location', $menu->getLocation() ) ?? $menu->getLocation() ) );

		if( $slugInput !== '' && $slugInput !== $menu->getSlug() )
		{
			$menu->setSlug( $this->uniqueSlug( $slugInput, $menu->getId() ) );
		}
		elseif( $slugInput === '' && $name !== $originalName )
		{
			$menu->setSlug( $this->uniqueSlug( $name, $menu->getId() ) );
		}

		try
		{
			$this->_repository->update( $menu );
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::SUCCESS->value, 'Menu updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Failed to update menu: ' . $e->getMessage() ] );
		}
	}

	#[Delete('/menus/:id', name: 'admin_menus_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$menu = $this->requireMenu( $request );

		try
		{
			$this->_repository->delete( $menu );
			$this->redirect( 'admin_menus', [], [ FlashMessageType::SUCCESS->value, 'Menu deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_menus', [], [ FlashMessageType::ERROR->value, 'Failed to delete menu: ' . $e->getMessage() ] );
		}
	}

	#[Get('/menus/:id/items/create', name: 'admin_menus_items_create')]
	public function createItem( Request $request ): string
	{
		$menu = $this->requireMenu( $request );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Menu Item | Admin' )
			->description( 'Add a menu item' )
			->withCurrentUser()
			->withCsrfToken()
			->with( $this->itemFormData( $menu, null ) )
			->render( 'item_create', 'admin' );
	}

	#[Post('/menus/:id/items', name: 'admin_menus_items_store', filters: ['csrf'])]
	public function storeItem( Request $request ): never
	{
		$menu  = $this->requireMenu( $request );
		$label = trim( (string) ( $request->post( 'label', '' ) ?? '' ) );

		if( $label === '' )
		{
			$this->redirect( 'admin_menus_items_create', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Label is required.' ] );
		}

		$item = $this->itemFromRequest( $request, $menu->getId() );

		if( $request->post( 'sort_order', null ) === null || trim( (string) $request->post( 'sort_order', '' ) ) === '' )
		{
			$item->setSortOrder( $this->_repository->nextItemSortOrder( $menu->getId(), $item->getParentId() ) );
		}

		try
		{
			$this->_repository->createItem( $item );
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::SUCCESS->value, 'Item added.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_menus_items_create', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Failed to add item: ' . $e->getMessage() ] );
		}
	}

	#[Get('/menus/:id/items/:itemId/edit', name: 'admin_menus_items_edit')]
	public function editItem( Request $request ): string
	{
		$menu = $this->requireMenu( $request );
		$item = $this->requireItem( $request, $menu );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Menu Item | Admin' )
			->description( 'Edit a menu item' )
			->withCurrentUser()
			->withCsrfToken()
			->with( $this->itemFormData( $menu, $item ) )
			->render( 'item_edit', 'admin' );
	}

	#[Put('/menus/:id/items/:itemId', name: 'admin_menus_items_update', filters: ['csrf'])]
	public function updateItem( Request $request ): never
	{
		$menu  = $this->requireMenu( $request );
		$item  = $this->requireItem( $request, $menu );
		$label = trim( (string) ( $request->post( 'label', '' ) ?? '' ) );

		if( $label === '' )
		{
			$this->redirect(
				'admin_menus_items_edit',
				[ 'id' => $menu->getId(), 'itemId' => $item->getId() ],
				[ FlashMessageType::ERROR->value, 'Label is required.' ]
			);
		}

		$updated = $this->itemFromRequest( $request, $menu->getId(), $item );

		try
		{
			$this->_repository->updateItem( $updated );
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::SUCCESS->value, 'Item updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_menus_items_edit',
				[ 'id' => $menu->getId(), 'itemId' => $item->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update item: ' . $e->getMessage() ]
			);
		}
	}

	#[Delete('/menus/:id/items/:itemId', name: 'admin_menus_items_destroy', filters: ['csrf'])]
	public function destroyItem( Request $request ): never
	{
		$menu = $this->requireMenu( $request );
		$item = $this->requireItem( $request, $menu );

		try
		{
			$this->_repository->deleteItem( $item );
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::SUCCESS->value, 'Item removed.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Failed to remove item: ' . $e->getMessage() ] );
		}
	}

	private function requireMenu( Request $request ): Menu
	{
		$id   = (int) $request->getRouteParameter( 'id' );
		$menu = $this->_repository->findById( $id );

		if( $menu === null )
		{
			$this->redirect( 'admin_menus', [], [ FlashMessageType::ERROR->value, 'Menu not found.' ] );
		}

		return $menu;
	}

	private function requireItem( Request $request, Menu $menu ): MenuItem
	{
		$itemId = (int) $request->getRouteParameter( 'itemId' );
		$item   = $this->_repository->findItemById( $itemId );

		if( $item === null || $item->getMenuId() !== $menu->getId() )
		{
			$this->redirect( 'admin_menus_edit', [ 'id' => $menu->getId() ], [ FlashMessageType::ERROR->value, 'Item not found.' ] );
		}

		return $item;
	}

	private function uniqueSlug( string $text, ?int $excludeId = null ): string
	{
		return $this->_slugs->generateUnique(
			$text,
			fn( string $slug ): bool => $this->_repository->slugExists( $slug, $excludeId ),
			'menu'
		);
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function itemFormData( Menu $menu, ?MenuItem $item ): array
	{
		$parents = [];

		foreach( $this->_repository->getItems( $menu->getId() ) as $candidate )
		{
			if( $candidate->getParentId() !== null )
			{
				continue;
			}

			if( $item && $candidate->getId() === $item->getId() )
			{
				continue;
			}

			$parents[] = $candidate;
		}

		return [
			'menu'      => $menu,
			'item'      => $item,
			'types'     => MenuItemType::cases(),
			'routes'    => MenuService::ROUTES,
			'pages'     => $this->_pages->getPublished(),
			'parents'   => $parents,
		];
	}

	private function itemFromRequest( Request $request, int $menuId, ?MenuItem $existing = null ): MenuItem
	{
		$item = $existing ?? new MenuItem();
		$item->setMenuId( $menuId );
		$item->setLabel( trim( (string) ( $request->post( 'label', '' ) ?? '' ) ) );
		$item->setItemType( (string) ( $request->post( 'item_type', MenuItemType::URL->value ) ?? MenuItemType::URL->value ) );

		$parentRaw = trim( (string) ( $request->post( 'parent_id', '' ) ?? '' ) );
		$item->setParentId( $parentRaw === '' ? null : (int) $parentRaw );

		$pageRaw = trim( (string) ( $request->post( 'page_id', '' ) ?? '' ) );
		$item->setPageId( $pageRaw === '' ? null : (int) $pageRaw );
		$item->setRouteName( $this->optional( $request, 'route_name' ) );
		$item->setUrl( $this->optional( $request, 'url' ) );
		$item->setTarget( (string) ( $request->post( 'target', '_self' ) ?? '_self' ) );
		$item->setIsVisible( (string) ( $request->post( 'is_visible', '1' ) ?? '1' ) !== '0' );
		$item->setSortOrder( (int) ( $request->post( 'sort_order', 0 ) ?? 0 ) );

		return $item;
	}
}
