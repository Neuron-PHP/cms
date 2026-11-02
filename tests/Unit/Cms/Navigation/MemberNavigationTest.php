<?php

namespace Tests\Cms\Navigation;

use Neuron\Cms\Navigation\MemberNavigation;
use Neuron\Cms\Navigation\NavItem;
use PHPUnit\Framework\TestCase;

class MemberNavigationTest extends TestCase
{
	protected function tearDown(): void
	{
		MemberNavigation::reset();
		parent::tearDown();
	}

	private static function keys( array $items ): array
	{
		return array_map( static fn( NavItem $item ) => $item->key, $items );
	}

	public function testDefaultsProvideDashboardAndProfile(): void
	{
		$nav = new MemberNavigation();

		$this->assertSame( [ 'dashboard' ], self::keys( $nav->items() ) );
		$this->assertSame( [ 'profile' ], self::keys( $nav->userItems() ) );
		$this->assertSame( 'member_dashboard', $nav->items()[0]->href );
		$this->assertSame( 'member_profile', $nav->userItems()[0]->href );
	}

	public function testCanStartEmpty(): void
	{
		$nav = new MemberNavigation( false );

		$this->assertSame( [], $nav->items() );
		$this->assertSame( [], $nav->userItems() );
	}

	public function testItemsSortByOrderThenRegistration(): void
	{
		$nav = ( new MemberNavigation( false ) )
			->add( new NavItem( 'billing', 'Billing', '/billing', 50 ) )
			->add( new NavItem( 'controls', 'Controls', '/controls', 10 ) )
			->add( new NavItem( 'documents', 'Documents', '/documents', 10 ) );

		$this->assertSame( [ 'controls', 'documents', 'billing' ], self::keys( $nav->items() ) );
	}

	public function testAddReplacesItemWithSameKey(): void
	{
		$nav = ( new MemberNavigation() )
			->add( new NavItem( 'dashboard', 'Home', '/dashboard' ) );

		$this->assertCount( 1, $nav->items() );
		$this->assertSame( 'Home', $nav->items()[0]->label );
		$this->assertSame( '/dashboard', $nav->items()[0]->href );
	}

	public function testRemoveDropsPrimaryAndUserItems(): void
	{
		$nav = ( new MemberNavigation() )
			->remove( 'dashboard' )
			->remove( 'profile' );

		$this->assertSame( [], $nav->items() );
		$this->assertSame( [], $nav->userItems() );
		$this->assertFalse( $nav->has( 'dashboard' ) );
	}

	public function testHiddenItemsAreFilteredAtReadTime(): void
	{
		$allowed = false;

		$nav = ( new MemberNavigation( false ) )
			->add( new NavItem( 'team', 'Team', '/team', 10, null, function() use ( &$allowed ) { return $allowed; } ) );

		$this->assertSame( [], $nav->items() );

		$allowed = true;

		$this->assertSame( [ 'team' ], self::keys( $nav->items() ) );
	}

	public function testInstanceIsSharedUntilReset(): void
	{
		MemberNavigation::instance()->add( new NavItem( 'controls', 'Controls', '/controls' ) );

		$this->assertTrue( MemberNavigation::instance()->has( 'controls' ) );

		MemberNavigation::reset();

		$this->assertFalse( MemberNavigation::instance()->has( 'controls' ) );
		$this->assertTrue( MemberNavigation::instance()->has( 'dashboard' ) );
	}
}
