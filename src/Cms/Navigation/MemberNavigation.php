<?php

namespace Neuron\Cms\Navigation;

/**
 * Navigation for the member shell (layouts/member.php).
 *
 * Applications built on the member shell register their links here, usually
 * from an initializer, instead of copying the layout:
 *
 * ```php
 * MemberNavigation::instance()
 *     ->remove( 'dashboard' )
 *     ->add( new NavItem( 'controls', 'Controls', '/controls', 20 ) );
 * ```
 *
 * Primary items appear in the top bar. User items appear in the account
 * dropdown, above the logout button, which the layout always renders.
 *
 * @package Neuron\Cms\Navigation
 */
final class MemberNavigation
{
	private static ?self $_instance = null;

	/** @var array<string, NavItem> */
	private array $_items = [];

	/** @var array<string, NavItem> */
	private array $_userItems = [];

	public function __construct( bool $withDefaults = true )
	{
		if( $withDefaults )
		{
			$this->add( new NavItem( 'dashboard', 'Dashboard', 'member_dashboard', 0, 'house-door' ) );
			$this->addUserItem( new NavItem( 'profile', 'Profile & Settings', 'member_profile', 0, 'person' ) );
		}
	}

	public static function instance(): self
	{
		return self::$_instance ??= new self();
	}

	/**
	 * Discard the shared instance (tests, or a site rebuilding from scratch).
	 */
	public static function reset(): void
	{
		self::$_instance = null;
	}

	/**
	 * Add or replace a primary navigation item.
	 */
	public function add( NavItem $item ): self
	{
		$this->_items[ $item->key ] = $item;
		return $this;
	}

	/**
	 * Add or replace an item in the account dropdown.
	 */
	public function addUserItem( NavItem $item ): self
	{
		$this->_userItems[ $item->key ] = $item;
		return $this;
	}

	/**
	 * Remove an item, primary or user, by key.
	 */
	public function remove( string $key ): self
	{
		unset( $this->_items[ $key ], $this->_userItems[ $key ] );
		return $this;
	}

	public function has( string $key ): bool
	{
		return isset( $this->_items[ $key ] ) || isset( $this->_userItems[ $key ] );
	}

	/**
	 * Visible primary items, sorted by order then registration.
	 *
	 * @return NavItem[]
	 */
	public function items(): array
	{
		return self::visibleSorted( $this->_items );
	}

	/**
	 * Visible account dropdown items, sorted by order then registration.
	 *
	 * @return NavItem[]
	 */
	public function userItems(): array
	{
		return self::visibleSorted( $this->_userItems );
	}

	/**
	 * @param array<string, NavItem> $items
	 * @return NavItem[]
	 */
	private static function visibleSorted( array $items ): array
	{
		$visible = array_values( array_filter( $items, static fn( NavItem $item ) => $item->isVisible() ) );

		// usort is stable in PHP 8+, so equal orders keep registration order.
		usort( $visible, static fn( NavItem $a, NavItem $b ) => $a->order <=> $b->order );

		return $visible;
	}
}
