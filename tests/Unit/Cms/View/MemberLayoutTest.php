<?php

namespace Tests\Cms\View;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\User;
use Neuron\Cms\Navigation\MemberNavigation;
use Neuron\Cms\Navigation\NavItem;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Renders the package member layout to check the shell contract:
 * registered navigation, the site header slot and flash messages.
 */
class MemberLayoutTest extends TestCase
{
	/** @var array<string, mixed> */
	private array $_previous = [];
	private string $_site;

	protected function setUp(): void
	{
		parent::setUp();
		require_once __DIR__ . '/../../../../src/Cms/View/helpers.php';
		require_once __DIR__ . '/../../../../src/Cms/Auth/helpers.php';

		foreach( [ 'App', RegistryKeys::VIEWS_PATH, RegistryKeys::SETTINGS, 'Auth.CsrfToken' ] as $key )
		{
			$this->_previous[ $key ] = Registry::getInstance()->get( $key );
		}

		$this->_site = sys_get_temp_dir() . '/member_layout_' . uniqid();
		mkdir( $this->_site, 0755, true );
		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $this->_site ] );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );
		Registry::getInstance()->set( 'Auth.CsrfToken', 'token' );

		$session = new SessionManager( [ 'test_mode' => true ] );
		$container = $this->createStub( IContainer::class );
		$container->method( 'has' )->willReturn( true );
		$container->method( 'get' )->willReturn( $session );
		$app = $this->createStub( IMvcApplication::class );
		$app->method( 'getContainer' )->willReturn( $container );
		Registry::getInstance()->set( 'App', $app );

		$_SESSION = [];
		$_SERVER['REQUEST_URI'] = '/controls/4';
	}

	protected function tearDown(): void
	{
		foreach( $this->_previous as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		@unlink( $this->_site . '/partials/member-header-end.php' );
		@rmdir( $this->_site . '/partials' );
		@rmdir( $this->_site );
		MemberNavigation::reset();
		$_SESSION = [];
		unset( $_SERVER['REQUEST_URI'] );

		parent::tearDown();
	}

	private function render(): string
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'lee' );
		$user->setEmail( 'lee@example.com' );

		$vars = [
			'Title'       => 'Controls',
			'Description' => '',
			'theme'       => 'sandstone',
			'siteName'    => 'PolicyHub',
			'currentUser' => $user,
			'appVersion'  => '',
			'content'     => '<main>page</main>',
		];

		return ( static function( array $vars ): string {
			extract( $vars );
			ob_start();
			require __DIR__ . '/../../../../resources/views/layouts/member.php';
			return (string)ob_get_clean();
		} )( $vars );
	}

	public function testRendersRegisteredNavigationWithActiveItem(): void
	{
		member_navigation()
			->remove( 'dashboard' )
			->add( new NavItem( 'controls', 'Controls', '/controls', 10 ) )
			->add( new NavItem( 'team', 'Team', '/team', 20, null, fn() => false ) )
			->addUserItem( new NavItem( 'billing', 'Billing', '/billing', 10 ) );

		$html = $this->render();

		$this->assertStringContainsString( '<a class="nav-link active" href="/controls" aria-current="page">', $html );
		$this->assertStringNotContainsString( 'Team', $html );
		$this->assertStringNotContainsString( 'member_dashboard', $html );
		$this->assertStringContainsString( 'href="/billing">Billing</a>', $html );
		$this->assertStringContainsString( '<main>page</main>', $html );
		$this->assertStringContainsString( 'Logout', $html );
	}

	public function testIncludesSiteHeaderSlot(): void
	{
		mkdir( $this->_site . '/partials', 0755, true );
		file_put_contents( $this->_site . '/partials/member-header-end.php', '<span id="org-switcher"><?= htmlspecialchars( $siteName ) ?></span>' );

		$this->assertStringContainsString( '<span id="org-switcher">PolicyHub</span>', $this->render() );
	}

	public function testRendersFlashMessagesOnce(): void
	{
		$_SESSION['_flash'] = [ 'success' => 'Saved.', 'error' => 'Nope <b>' ];

		$html = $this->render();

		$this->assertStringContainsString( 'alert alert-success', $html );
		$this->assertStringContainsString( 'Saved.', $html );
		$this->assertStringContainsString( 'alert alert-danger', $html );
		$this->assertStringContainsString( 'Nope &lt;b&gt;', $html );
		$this->assertStringNotContainsString( 'Saved.', $this->render() );
	}
}
