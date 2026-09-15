<?php

namespace Neuron\Cms\Models;

use DateTimeImmutable;
use Exception;
use Neuron\Cms\Enums\MenuItemType;
use Neuron\Orm\Model;
use Neuron\Orm\Attributes\{Table, BelongsTo};

/**
 * A link in a CMS-managed menu.
 *
 * @package Neuron\Cms\Models
 */
#[Table('menu_items')]
class MenuItem extends Model
{
	private ?int $_id = null;
	private int $_menuId = 0;
	private ?int $_parentId = null;
	private string $_label = '';
	private string $_itemType = MenuItemType::URL->value;
	private ?int $_pageId = null;
	private ?string $_routeName = null;
	private ?string $_url = null;
	private string $_target = '_self';
	private int $_sortOrder = 0;
	private bool $_isVisible = true;
	private ?DateTimeImmutable $_createdAt = null;
	private ?DateTimeImmutable $_updatedAt = null;

	#[BelongsTo(Menu::class, foreignKey: 'menu_id')]
	private ?Menu $_menu = null;

	public function __construct()
	{
		$this->_createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->_id;
	}

	public function setId( int $id ): self
	{
		$this->_id = $id;
		return $this;
	}

	public function getMenuId(): int
	{
		return $this->_menuId;
	}

	public function setMenuId( int $menuId ): self
	{
		$this->_menuId = $menuId;
		return $this;
	}

	public function getParentId(): ?int
	{
		return $this->_parentId;
	}

	public function setParentId( ?int $parentId ): self
	{
		$this->_parentId = $parentId;
		return $this;
	}

	public function getLabel(): string
	{
		return $this->_label;
	}

	public function setLabel( string $label ): self
	{
		$this->_label = $label;
		return $this;
	}

	public function getItemType(): string
	{
		return $this->_itemType;
	}

	public function getItemTypeMode(): MenuItemType
	{
		return MenuItemType::fromValue( $this->_itemType );
	}

	public function setItemType( string $itemType ): self
	{
		$this->_itemType = MenuItemType::fromValue( $itemType )->value;
		return $this;
	}

	public function getPageId(): ?int
	{
		return $this->_pageId;
	}

	public function setPageId( ?int $pageId ): self
	{
		$this->_pageId = $pageId;
		return $this;
	}

	public function getRouteName(): ?string
	{
		return $this->_routeName;
	}

	public function setRouteName( ?string $routeName ): self
	{
		$this->_routeName = $routeName;
		return $this;
	}

	public function getUrl(): ?string
	{
		return $this->_url;
	}

	public function setUrl( ?string $url ): self
	{
		$this->_url = $url;
		return $this;
	}

	public function getTarget(): string
	{
		return $this->_target;
	}

	public function setTarget( string $target ): self
	{
		$this->_target = $target === '_blank' ? '_blank' : '_self';
		return $this;
	}

	public function getSortOrder(): int
	{
		return $this->_sortOrder;
	}

	public function setSortOrder( int $sortOrder ): self
	{
		$this->_sortOrder = $sortOrder;
		return $this;
	}

	public function isVisible(): bool
	{
		return $this->_isVisible;
	}

	public function setIsVisible( bool $isVisible ): self
	{
		$this->_isVisible = $isVisible;
		return $this;
	}

	public function getCreatedAt(): ?DateTimeImmutable
	{
		return $this->_createdAt;
	}

	public function setCreatedAt( DateTimeImmutable $createdAt ): self
	{
		$this->_createdAt = $createdAt;
		return $this;
	}

	public function getUpdatedAt(): ?DateTimeImmutable
	{
		return $this->_updatedAt;
	}

	public function setUpdatedAt( ?DateTimeImmutable $updatedAt ): self
	{
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	public function getMenu(): ?Menu
	{
		return $this->_menu;
	}

	public function setMenu( ?Menu $menu ): self
	{
		$this->_menu = $menu;
		return $this;
	}

	/**
	 * @param array<string, mixed> $data
	 * @throws Exception
	 */
	public static function fromArray( array $data ): static
	{
		$item = new self();

		if( isset( $data['id'] ) )
		{
			$item->setId( (int) $data['id'] );
		}

		$item->setMenuId( (int) ( $data['menu_id'] ?? 0 ) );
		$item->setParentId( isset( $data['parent_id'] ) && $data['parent_id'] !== '' && $data['parent_id'] !== null
			? (int) $data['parent_id']
			: null );
		$item->setLabel( $data['label'] ?? '' );
		$item->setItemType( $data['item_type'] ?? MenuItemType::URL->value );
		$item->setPageId( isset( $data['page_id'] ) && $data['page_id'] !== '' && $data['page_id'] !== null
			? (int) $data['page_id']
			: null );
		$item->setRouteName( $data['route_name'] ?? null );
		$item->setUrl( $data['url'] ?? null );
		$item->setTarget( $data['target'] ?? '_self' );
		$item->setSortOrder( (int) ( $data['sort_order'] ?? 0 ) );
		$item->setIsVisible( (bool) ( $data['is_visible'] ?? true ) );

		if( isset( $data['created_at'] ) && $data['created_at'] )
		{
			$item->setCreatedAt(
				is_string( $data['created_at'] )
					? new DateTimeImmutable( $data['created_at'] )
					: $data['created_at']
			);
		}

		if( isset( $data['updated_at'] ) && $data['updated_at'] )
		{
			$item->setUpdatedAt(
				is_string( $data['updated_at'] )
					? new DateTimeImmutable( $data['updated_at'] )
					: $data['updated_at']
			);
		}

		return $item;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->_id,
			'menu_id' => $this->_menuId,
			'parent_id' => $this->_parentId,
			'label' => $this->_label,
			'item_type' => $this->_itemType,
			'page_id' => $this->_pageId,
			'route_name' => $this->_routeName,
			'url' => $this->_url,
			'target' => $this->_target,
			'sort_order' => $this->_sortOrder,
			'is_visible' => $this->_isVisible,
			'created_at' => $this->_createdAt?->format( 'Y-m-d H:i:s' ),
			'updated_at' => $this->_updatedAt?->format( 'Y-m-d H:i:s' ),
		];
	}
}
