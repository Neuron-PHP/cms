<?php

namespace Tests\Cms\View;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Navigation\MemberNavigation;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class MemberShellHelpersTest extends TestCase
{
	private mixed $_previousSettings;
	private mixed $_previousApp;
	private mixed $_previousViews;

	protected function setUp(): void
	{
		parent::setUp();
		require_once __DIR__ . '/../../../../src/Cms/View/helpers.php';

		$registry = Registry::getInstance();
		$this->_previousSettings = $registry->get( RegistryKeys::SETTINGS );
		$this->_previousApp = $registry->get( 'App' );
		$this->_previousViews = $registry->get( RegistryKeys::VIEWS_PATH );

		// No application: route_path() resolves nothing.
		$registry->set( 'App', null );
		$_SESSION = [];
	}

	protected function tearDown(): void
	{
		$registry = Registry::getInstance();
		$registry->set( RegistryKeys::SETTINGS, $this->_previousSettings );
		$registry->set( 'App', $this->_previousApp );
		$registry->set( RegistryKeys::VIEWS_PATH, $this->_previousViews );
		MemberNavigation::reset();
		$_SESSION = [];

		parent::tearDown();
	}

	private function settings( array $member ): void
	{
		$source = new Memory();

		foreach( $member as $name => $value )
		{
			$source->set( 'member', $name, $value );
		}

		Registry::getInstance()->set( RegistryKeys::SETTINGS, new SettingManager( $source ) );
	}

	private function useSession( SessionManager $session ): void
	{
		$container = $this->createStub( IContainer::class );
		$container->method( 'has' )->willReturn( true );
		$container->method( 'get' )->willReturn( $session );

		$app = $this->createStub( IMvcApplication::class );
		$app->method( 'getContainer' )->willReturn( $container );
		Registry::getInstance()->set( 'App', $app );
	}

	public function testMemberNavigationReturnsSharedRegistry(): void
	{
		$this->assertSame( MemberNavigation::instance(), member_navigation() );
	}

	public function testMemberHomeDefaultsToMemberDashboard(): void
	{
		$this->settings( [] );

		$this->assertSame( '/member/dashboard', member_home() );
	}

	public function testMemberHomeUsesConfiguredUrl(): void
	{
		$this->settings( [ 'home' => '/dashboard' ] );

		$this->assertSame( '/dashboard', member_home() );
	}

	public function testMemberHomeWithUnresolvableRouteFallsBackToRoot(): void
	{
		$this->settings( [ 'home' => 'no_such_route' ] );

		$this->assertSame( '/', member_home() );
	}

	public function testMemberFlashReturnsAllMessagesOnceInOrder(): void
	{
		$session = new SessionManager( [ 'test_mode' => true ] );
		$session->flash( 'success', 'Saved.' );
		$session->flash( 'error', [ 'Name is required', 'Email is invalid' ] );
		$this->useSession( $session );

		$this->assertSame( [
			[ 'type' => 'success', 'message' => 'Saved.' ],
			[ 'type' => 'error', 'message' => 'Name is required' ],
			[ 'type' => 'error', 'message' => 'Email is invalid' ],
		], member_flash() );

		$this->assertSame( [], member_flash() );
	}

	public function testMemberFlashToleratesLegacyStringFlash(): void
	{
		$this->useSession( new SessionManager( [ 'test_mode' => true ] ) );
		$_SESSION['_flash'] = 'Checkout canceled.';

		$this->assertSame( [ [ 'type' => 'info', 'message' => 'Checkout canceled.' ] ], member_flash() );
		$this->assertArrayNotHasKey( '_flash', $_SESSION );
	}

	public function testMemberHeaderSlotIsNullWithoutSitePartial(): void
	{
		$site = sys_get_temp_dir() . '/member_slot_' . uniqid();
		mkdir( $site, 0755, true );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $site ] );

		try
		{
			$this->assertNull( member_header_slot() );
		}
		finally
		{
			rmdir( $site );
		}
	}

	public function testMemberHeaderSlotFindsSitePartial(): void
	{
		$site = sys_get_temp_dir() . '/member_slot_' . uniqid();
		mkdir( $site . '/partials', 0755, true );
		file_put_contents( $site . '/partials/member-header-end.php', 'slot' );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $site ] );

		try
		{
			$this->assertSame( $site . '/partials/member-header-end.php', member_header_slot() );
		}
		finally
		{
			unlink( $site . '/partials/member-header-end.php' );
			rmdir( $site . '/partials' );
			rmdir( $site );
		}
	}
}
