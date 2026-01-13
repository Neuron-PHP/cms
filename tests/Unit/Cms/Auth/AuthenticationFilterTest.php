<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Cms\Auth\Filters\AuthenticationFilter;
use Neuron\Cms\Models\User;
use Neuron\Routing\RouteMap;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for admin authentication filter
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AuthenticationFilterTest extends TestCase
{
	private Authentication $_authentication;
	private AuthenticationFilter $_filter;
	private RouteMap $_route;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock auth manager
		$this->_authentication = $this->createMock( Authentication::class );

		// Create filter
		$this->_filter = new AuthenticationFilter( $this->_authentication, '/login' );

		// Create mock route
		$this->_route = $this->createMock( RouteMap::class );
		$this->_route->method( 'getPath' )->willReturn( '/admin/users' );

		// Clear Registry
		Registry::getInstance()->reset();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		Registry::getInstance()->reset();
	}

	public function testConstructor(): void
	{
		$this->assertInstanceOf( AuthenticationFilter::class, $this->_filter );
	}

	public function testSetLoginUrl(): void
	{
		$result = $this->_filter->setLoginUrl( '/custom-login' );
		$this->assertInstanceOf( AuthenticationFilter::class, $result );
	}

	public function testSetsUserInRegistryForAuthenticatedUser(): void
	{
		// Create authenticated user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'admin' );
		$user->setRole( User::ROLE_ADMIN );

		// Auth manager returns user
		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		// Execute filter
		$this->_filter->pre( $this->_route );

		// Verify user was set in Registry
		$this->assertEquals( $user, Registry::getInstance()->get( RegistryKeys::AUTH_USER ) );
		$this->assertEquals( 1, Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ) );
		$this->assertEquals( User::ROLE_ADMIN, Registry::getInstance()->get( 'Auth.UserRole' ) );
	}

	public function testSetsUserIdCorrectly(): void
	{
		$user = new User();
		$user->setId( 42 );
		$user->setUsername( 'testuser' );
		$user->setRole( User::ROLE_EDITOR );

		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		$this->_filter->pre( $this->_route );

		$this->assertEquals( 42, Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ) );
	}

	public function testSetsUserRoleCorrectly(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'author' );
		$user->setRole( User::ROLE_AUTHOR );

		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		$this->_filter->pre( $this->_route );

		$this->assertEquals( User::ROLE_AUTHOR, Registry::getInstance()->get( 'Auth.UserRole' ) );
	}

	/**
	 * Test that filter throws exception for unauthenticated user.
	 *
	 * When user is null (not authenticated), the filter should throw
	 * UnauthenticatedException with redirect URL to login page.
	 */
	public function testThrowsExceptionForUnauthenticatedUser(): void
	{
		// Mock authentication to return null (unauthenticated)
		$this->_authentication
			->method( 'user' )
			->willReturn( null );

		// Expect UnauthenticatedException when user is not authenticated
		$this->expectException( \Neuron\Cms\Exceptions\UnauthenticatedException::class );
		$this->expectExceptionCode( 401 );

		// Execute filter - should throw exception
		$this->_filter->pre( $this->_route );
	}

	/**
	 * Test that UnauthenticatedException includes redirect information.
	 *
	 * The exception should include the redirect URL (login page) and
	 * the intended URL (original page user was trying to access).
	 */
	public function testExceptionIncludesRedirectInformation(): void
	{
		// Mock authentication to return null
		$this->_authentication
			->method( 'user' )
			->willReturn( null );

		try
		{
			$this->_filter->pre( $this->_route );
			$this->fail( 'Expected UnauthenticatedException was not thrown' );
		}
		catch( \Neuron\Cms\Exceptions\UnauthenticatedException $e )
		{
			// Verify exception includes redirect URL (login page with query param)
			$redirectUrl = $e->getRedirectUrl();
			$this->assertStringContainsString( '/login', $redirectUrl );
			$this->assertStringContainsString( 'redirect=', $redirectUrl );

			// Verify exception includes intended URL (original route path)
			$this->assertEquals( '/admin/users', $e->getIntendedUrl() );

			// Verify exception code
			$this->assertEquals( 401, $e->getCode() );
		}
	}

	/**
	 * Test that filter does not populate Registry for unauthenticated user.
	 *
	 * When authentication fails, Registry should remain unpopulated.
	 */
	public function testDoesNotPopulateRegistryForNullUser(): void
	{
		// Mock authentication to return null (unauthenticated)
		$this->_authentication
			->method( 'user' )
			->willReturn( null );

		// Verify Registry is not populated before filter runs
		$this->assertNull( Registry::getInstance()->get( RegistryKeys::AUTH_USER ) );
		$this->assertNull( Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ) );
		$this->assertNull( Registry::getInstance()->get( 'Auth.UserRole' ) );

		try
		{
			$this->_filter->pre( $this->_route );
		}
		catch( \Neuron\Cms\Exceptions\UnauthenticatedException $e )
		{
			// Expected exception - verify Registry still not populated
			$this->assertNull(
				Registry::getInstance()->get( RegistryKeys::AUTH_USER ),
				'Auth.User should not be set when authentication fails'
			);
			$this->assertNull(
				Registry::getInstance()->get( RegistryKeys::AUTH_USER_ID ),
				'Auth.UserId should not be set when authentication fails'
			);
			$this->assertNull(
				Registry::getInstance()->get( 'Auth.UserRole' ),
				'Auth.UserRole should not be set when authentication fails'
			);
		}
	}

	public function testHandlesDifferentUserRoles(): void
	{
		$roles = [
			User::ROLE_ADMIN,
			User::ROLE_EDITOR,
			User::ROLE_AUTHOR,
			User::ROLE_SUBSCRIBER
		];

		foreach( $roles as $role )
		{
			// Clear registry for each iteration
			Registry::getInstance()->reset();

			$user = new User();
			$user->setId( 1 );
			$user->setUsername( 'testuser' );
			$user->setRole( $role );

			$this->_authentication = $this->createMock( Authentication::class );
			$this->_authentication->method( 'user' )->willReturn( $user );

			$filter = new AuthenticationFilter( $this->_authentication );
			$filter->pre( $this->_route );

			$this->assertEquals( $role, Registry::getInstance()->get( 'Auth.UserRole' ), "Failed for role: $role" );
		}
	}

	public function testCustomLoginUrl(): void
	{
		$customFilter = new AuthenticationFilter( $this->_authentication, '/custom-auth' );
		$this->assertInstanceOf( AuthenticationFilter::class, $customFilter );

		$customFilter->setLoginUrl( '/another-login' );
		$this->assertInstanceOf( AuthenticationFilter::class, $customFilter );
	}
}
