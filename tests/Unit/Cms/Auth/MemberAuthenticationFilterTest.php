<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Auth\Filters\MemberAuthenticationFilter;
use Neuron\Cms\Models\User;
use Neuron\Routing\RouteMap;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MemberAuthenticationFilterTest extends TestCase
{
	private Authentication $_authentication;
	private MemberAuthenticationFilter $_filter;
	private RouteMap $_route;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock auth manager
		$this->_authentication = $this->createMock( Authentication::class );

		// Create filter
		$this->_filter = new MemberAuthenticationFilter( $this->_authentication, '/login', true );

		// Create mock route
		$this->_route = $this->createMock( RouteMap::class );
		$this->_route->method( 'getPath' )->willReturn( '/member/dashboard' );

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
		$this->assertInstanceOf( MemberAuthenticationFilter::class, $this->_filter );
	}

	public function testSetLoginUrl(): void
	{
		$result = $this->_filter->setLoginUrl( '/custom-login' );
		$this->assertInstanceOf( MemberAuthenticationFilter::class, $result );
	}

	public function testSetVerifyEmailUrl(): void
	{
		$result = $this->_filter->setVerifyEmailUrl( '/custom-verify' );
		$this->assertInstanceOf( MemberAuthenticationFilter::class, $result );
	}

	public function testSetRequireEmailVerification(): void
	{
		$result = $this->_filter->setRequireEmailVerification( false );
		$this->assertInstanceOf( MemberAuthenticationFilter::class, $result );
	}

	public function testBeforeWithAuthenticatedAndVerifiedUser(): void
	{
		// Create verified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmailVerified( true );

		// Auth manager returns user
		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		// This should set user in Registry and not redirect
		$this->_filter->pre( $this->_route );

		// Verify user was set in Registry
		$this->assertEquals( $user, Registry::getInstance()->get( 'Auth.User' ) );
		$this->assertEquals( 1, Registry::getInstance()->get( 'Auth.UserId' ) );
	}

	/**
	 * Test that filter throws exception for unauthenticated user.
	 */
	public function testBeforeWithUnauthenticatedUser(): void
	{
		// Auth manager returns null (no user)
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
	 * Test that filter throws exception for unverified user.
	 */
	public function testBeforeWithUnverifiedUser(): void
	{
		// Create unverified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmailVerified( false );

		// Auth manager returns unverified user
		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		// Filter requires verification
		$filter = new MemberAuthenticationFilter( $this->_authentication, '/login', true );

		// Expect EmailVerificationRequiredException when user is not verified
		$this->expectException( \Neuron\Cms\Exceptions\EmailVerificationRequiredException::class );
		$this->expectExceptionCode( 403 );

		// Execute filter - should throw exception
		$filter->pre( $this->_route );
	}

	public function testBeforeWithUnverifiedUserButVerificationNotRequired(): void
	{
		// Create unverified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmailVerified( false );

		// Auth manager returns unverified user
		$this->_authentication
			->method( 'user' )
			->willReturn( $user );

		// Filter doesn't require verification
		$filter = new MemberAuthenticationFilter( $this->_authentication, '/login', false );

		// This should NOT redirect, even though user is unverified
		$filter->pre( $this->_route );

		// Verify user was set in Registry
		$this->assertEquals( $user, Registry::getInstance()->get( 'Auth.User' ) );
	}
}
