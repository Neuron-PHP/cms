<?php

namespace Tests\Cms\Controllers\Member;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\User;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;
use Tests\Cms\Controllers\Member\Fixture\ShellPageController;

require_once __DIR__ . '/Fixture/ShellPageController.php';

class MemberControllerTest extends TestCase
{
	private string $_views;
	private string $_versionFile;

	/** @var array<string, mixed> */
	private array $_previous = [];

	private const KEYS = [
		RegistryKeys::SETTINGS,
		RegistryKeys::VIEWS_PATH,
		RegistryKeys::AUTH_USER,
		RegistryKeys::APP_VERSION,
		RegistryKeys::APP_NAME,
		RegistryKeys::APP_RSS_URL,
		'Auth.CsrfToken',
		'App',
		'ViewDataProvider',
	];

	protected function setUp(): void
	{
		parent::setUp();
		require_once __DIR__ . '/../../../../../src/Cms/View/helpers.php';

		foreach( self::KEYS as $key )
		{
			$this->_previous[ $key ] = Registry::getInstance()->get( $key );
		}

		$this->_views = sys_get_temp_dir() . '/member_controller_' . uniqid();
		mkdir( $this->_views . '/layouts', 0755, true );
		mkdir( $this->_views . '/member/fixture/shell_page', 0755, true );
		mkdir( $this->_views . '/errors', 0755, true );
		file_put_contents( $this->_views . '/errors/404.php', '<p>missing <?= $what ?></p>' );
		file_put_contents( $this->_views . '/layouts/member.php', '<shell title="<?= $Title ?? \'\' ?>"><?= $content ?></shell>' );
		file_put_contents(
			$this->_views . '/member/fixture/shell_page/show.php',
			'<p><?= htmlspecialchars( $greeting ) ?> user=<?= isset( $User ) ? $User->getId() : \'none\' ?></p>'
		);

		$this->_versionFile = sys_get_temp_dir() . '/member_controller_version_' . uniqid() . '.json';
		file_put_contents( $this->_versionFile, json_encode( [ 'major' => 1, 'minor' => 0, 'patch' => 0 ] ) );

		Registry::getInstance()->set( RegistryKeys::VIEWS_PATH, [ $this->_views ] );
		Registry::getInstance()->set( 'App', null );
		Registry::getInstance()->set( 'ViewDataProvider', null );
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, null );
		$_SESSION = [];
	}

	protected function tearDown(): void
	{
		foreach( $this->_previous as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		@unlink( $this->_views . '/errors/404.php' );
		@rmdir( $this->_views . '/errors' );
		@unlink( $this->_views . '/layouts/member.php' );
		@unlink( $this->_views . '/member/fixture/shell_page/show.php' );
		@rmdir( $this->_views . '/member/fixture/shell_page' );
		@rmdir( $this->_views . '/member/fixture' );
		@rmdir( $this->_views . '/member' );
		@rmdir( $this->_views . '/layouts' );
		@rmdir( $this->_views );
		@unlink( $this->_versionFile );
		$_SESSION = [];

		parent::tearDown();
	}

	private function controller( array $member = [] ): ShellPageController
	{
		$source = new Memory();
		$source->set( 'site', 'name', 'PolicyHub' );
		$source->set( 'paths', 'version_file', $this->_versionFile );

		foreach( $member as $name => $value )
		{
			$source->set( 'member', $name, $value );
		}

		$settings = new SettingManager( $source );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$app = $this->createStub( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $this->createStub( Router::class ) );

		return new ShellPageController( $app, $settings, new SessionManager( [ 'test_mode' => true ] ) );
	}

	private function user( int $id ): User
	{
		$user = new User();
		$user->setId( $id );

		return $user;
	}

	public function testRenderPageUsesControllerViewDirectoryInMemberLayout(): void
	{
		$html = $this->controller()->show( [ 'greeting' => 'Hello <b>' ], 'Controls' );

		$this->assertSame( '<shell title="Controls | PolicyHub"><p>Hello &lt;b&gt; user=none</p></shell>', $html );
	}

	public function testRenderPageInjectsCurrentUser(): void
	{
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $this->user( 7 ) );

		$html = $this->controller()->show( [ 'greeting' => 'Hi' ] );

		$this->assertStringContainsString( 'user=7', $html );
	}

	public function testRenderPageInitializesCsrfToken(): void
	{
		$this->controller()->show( [ 'greeting' => 'Hi' ] );

		$this->assertNotEmpty( Registry::getInstance()->get( 'Auth.CsrfToken' ) );
	}

	public function testRenderPageSetsStatus(): void
	{
		$this->controller()->show( [ 'greeting' => 'Hi' ], null, HttpResponseStatus::NOT_FOUND );

		$this->assertSame( 404, http_response_code() );
	}

	public function testCurrentUserIdReadsAuthenticatedUser(): void
	{
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $this->user( 12 ) );

		$this->assertSame( 12, $this->controller()->userId() );
	}

	public function testConfiguredMemberHomeIsNullWhenUnset(): void
	{
		$this->assertNull( $this->controller()->memberHome() );
	}

	public function testConfiguredMemberHomeReturnsUrl(): void
	{
		$this->assertSame( '/dashboard', $this->controller( [ 'home' => '/dashboard' ] )->memberHome() );
	}

	public function testRenderViewUsesExplicitDirectory(): void
	{
		$controller = $this->controller();

		$this->assertSame( '<shell title=""><p>missing control</p></shell>', $controller->showView( 'errors/404', [ 'what' => 'control' ] ) );
		$this->assertSame( 404, http_response_code() );
	}

	public function testRenderViewRestoresControllerDirectory(): void
	{
		$controller = $this->controller();
		$controller->showView( 'errors/404', [ 'what' => 'x' ] );

		$this->assertStringContainsString( '<p>Hi user=none</p>', $controller->show( [ 'greeting' => 'Hi' ] ) );
	}

	public function testRenderViewWithoutDirectoryUsesControllerDirectory(): void
	{
		$this->assertStringContainsString( '<p>Hi user=none</p>', $this->controller()->showView( 'show', [ 'greeting' => 'Hi' ] ) );
	}
}
