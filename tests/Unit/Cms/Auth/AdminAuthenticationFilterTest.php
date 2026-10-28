<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Auth\Filters\AdminAuthenticationFilter;
use Neuron\Cms\Exceptions\UnauthenticatedException;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Core\Exceptions\Forbidden;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Patterns\Registry;
use Neuron\Routing\RouteMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]

class AdminAuthenticationFilterTest extends TestCase
{
	private Authentication $_authentication;
	private RouteMap $_route;

	protected function setUp(): void
	{
		parent::setUp();

		$this->_authentication = $this->createMock( Authentication::class );

		$this->_route = $this->createMock( RouteMap::class );
		$this->_route->method( 'getPath' )->willReturn( '/admin/dashboard' );

		Registry::getInstance()->reset();
		unset( $_SERVER['REQUEST_URI'] );
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	private function user( int $id, string $role ): User
	{
		$user = new User();
		$user->setId( $id );
		$user->setUsername( $role );
		$user->setRole( $role );

		return $user;
	}

	private function filter( array $roles = AdminAuthenticationFilter::DEFAULT_ROLES ): AdminAuthenticationFilter
	{
		return new AdminAuthenticationFilter( $this->_authentication, $roles );
	}

	public function testDefaultRolesAreStaffOnly(): void
	{
		$this->assertSame(
			[ User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR ],
			$this->filter()->getRoles()
		);
		$this->assertNotContains( User::ROLE_SUBSCRIBER, AdminAuthenticationFilter::DEFAULT_ROLES );
	}

	/**
	 * The regression this filter exists for.
	 */
	public function testSubscriberIsForbidden(): void
	{
		$this->_authentication->method( 'user' )->willReturn( $this->user( 7, User::ROLE_SUBSCRIBER ) );

		try
		{
			$this->filter()->pre( $this->_route );
			$this->fail( 'Expected Forbidden' );
		}
		catch( Forbidden $e )
		{
			$this->assertSame( 403, $e->getCode() );
			$this->assertSame( '/admin/dashboard', $e->getResource() );
			$this->assertSame( 'admin', $e->getPermission() );
		}

		$this->assertNull( Registry::getInstance()->get( RegistryKeys::AUTH_USER ), 'refused user must not be published' );
	}

	#[DataProvider( 'staffRoles' )]
	public function testStaffRolesPassAndArePublished( string $role ): void
	{
		$user = $this->user( 3, $role );
		$this->_authentication->method( 'user' )->willReturn( $user );

		$this->filter()->pre( $this->_route );

		$this->assertSame( $user, Registry::getInstance()->get( RegistryKeys::AUTH_USER ) );
		$this->assertSame( 3, Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ) );
		$this->assertSame( $role, Registry::getInstance()->get( RegistryKeys::AUTH_USER_ROLE ) );
	}

	public static function staffRoles(): array
	{
		return [
			'admin'  => [ User::ROLE_ADMIN ],
			'editor' => [ User::ROLE_EDITOR ],
			'author' => [ User::ROLE_AUTHOR ],
		];
	}

	public function testAnonymousVisitorIsRedirectedToLogin(): void
	{
		$this->_authentication->method( 'user' )->willReturn( null );
		$_SERVER['REQUEST_URI'] = '/admin/users?page=2';

		try
		{
			$this->filter()->pre( $this->_route );
			$this->fail( 'Expected UnauthenticatedException' );
		}
		catch( UnauthenticatedException $e )
		{
			$this->assertSame( '/login?redirect=' . urlencode( '/admin/users?page=2' ), $e->getRedirectUrl() );
		}
	}

	public function testCustomRoleListIsHonoured(): void
	{
		$this->_authentication->method( 'user' )->willReturn( $this->user( 9, User::ROLE_AUTHOR ) );

		$this->expectException( Forbidden::class );

		$this->filter( [ User::ROLE_ADMIN, User::ROLE_EDITOR ] )->pre( $this->_route );
	}

	public function testAuthenticationCanBeResolvedLazily(): void
	{
		$this->_authentication->method( 'user' )->willReturn( $this->user( 1, User::ROLE_ADMIN ) );

		$calls = 0;
		$filter = new AdminAuthenticationFilter( function() use ( &$calls ) {
			$calls++;
			return $this->_authentication;
		} );

		$this->assertSame( 0, $calls, 'not resolved at construction' );

		$filter->pre( $this->_route );
		$filter->pre( $this->_route );

		$this->assertSame( 1, $calls, 'resolved once, then cached' );
	}

	public function testSetLoginUrl(): void
	{
		$this->_authentication->method( 'user' )->willReturn( null );

		$filter = $this->filter()->setLoginUrl( '/staff/login?x=1' );

		try
		{
			$filter->pre( $this->_route );
			$this->fail( 'Expected UnauthenticatedException' );
		}
		catch( UnauthenticatedException $e )
		{
			$this->assertStringStartsWith( '/staff/login?x=1&redirect=', $e->getRedirectUrl() );
		}
	}
}
