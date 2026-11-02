<?php

namespace Tests\Cms\Navigation;

use Neuron\Cms\Navigation\NavItem;
use PHPUnit\Framework\TestCase;

class NavItemTest extends TestCase
{
	private static function resolver(): callable
	{
		return static fn( string $route ) => [ 'member_profile' => '/member/profile' ][ $route ] ?? '';
	}

	public function testUrlsArePassedThrough(): void
	{
		$this->assertSame( '/controls', ( new NavItem( 'c', 'Controls', '/controls' ) )->url( self::resolver() ) );
		$this->assertSame( 'https://example.com/x', ( new NavItem( 'x', 'X', 'https://example.com/x' ) )->url( self::resolver() ) );
		$this->assertSame( '#help', ( new NavItem( 'h', 'Help', '#help' ) )->url( self::resolver() ) );
	}

	public function testRouteNamesResolve(): void
	{
		$this->assertSame( '/member/profile', ( new NavItem( 'p', 'Profile', 'member_profile' ) )->url( self::resolver() ) );
	}

	public function testUnknownRouteFallsBackToHash(): void
	{
		$this->assertSame( '#', ( new NavItem( 'u', 'Unknown', 'no_such_route' ) )->url( self::resolver() ) );
	}

	public function testActiveMatchesExactPathAndChildren(): void
	{
		$item = new NavItem( 'c', 'Controls', '/controls' );

		$this->assertTrue( $item->isActive( '/controls' ) );
		$this->assertTrue( $item->isActive( '/controls/' ) );
		$this->assertTrue( $item->isActive( '/controls/12/edit' ) );
		$this->assertFalse( $item->isActive( '/control-categories' ) );
		$this->assertFalse( $item->isActive( '/dashboard' ) );
	}

	public function testHashLinksAreNeverActive(): void
	{
		$this->assertFalse( ( new NavItem( 'h', 'Help', '#' ) )->isActive( '/' ) );
	}

	public function testVisibilityDefaultsToTrue(): void
	{
		$this->assertTrue( ( new NavItem( 'c', 'Controls', '/controls' ) )->isVisible() );
		$this->assertFalse( ( new NavItem( 'c', 'Controls', '/controls', 1, null, fn() => false ) )->isVisible() );
	}
}
