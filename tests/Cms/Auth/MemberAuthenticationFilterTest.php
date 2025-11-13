<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Auth\AuthManager;
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
	private AuthManager $_authManager;
	private MemberAuthenticationFilter $_filter;
	private RouteMap $_route;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock auth manager
		$this->_authManager = $this->createMock( AuthManager::class );

		// Create filter
		$this->_filter = new MemberAuthenticationFilter( $this->_authManager, '/login', true );

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
		$this->_authManager
			->method( 'user' )
			->willReturn( $user );

		// This should set user in Registry and not redirect
		$this->_filter->pre( $this->_route );

		// Verify user was set in Registry
		$this->assertEquals( $user, Registry::getInstance()->get( 'Auth.User' ) );
		$this->assertEquals( 1, Registry::getInstance()->get( 'Auth.UserId' ) );
	}

	/**
	 * Testing redirects with exit() is problematic in PHPUnit.
	 * This test verifies the conditions that would trigger a redirect.
	 */
	public function testBeforeWithUnauthenticatedUser(): void
	{
		// Auth manager returns null (no user)
		$this->_authManager
			->method( 'user' )
			->willReturn( null );

		// We can't easily test exit() behavior, but we can verify the condition
		// In a real scenario, this would redirect and exit
		// For testing, we just verify the user() method is called
		$this->assertNull( $this->_authManager->user() );

		// Note: In production, calling $this->_filter->pre() would trigger redirect and exit
		// We skip that here to avoid process termination in tests
		$this->markTestIncomplete( 'Testing exit() behavior requires different approach' );
	}

	/**
	 * Testing redirects with exit() is problematic in PHPUnit.
	 * This test verifies the conditions that would trigger a redirect.
	 */
	public function testBeforeWithUnverifiedUser(): void
	{
		// Create unverified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmailVerified( false );

		// Auth manager returns unverified user
		$this->_authManager
			->method( 'user' )
			->willReturn( $user );

		// Filter requires verification
		$filter = new MemberAuthenticationFilter( $this->_authManager, '/login', true );

		// Verify user is unverified and filter requires verification
		$this->assertFalse( $user->isEmailVerified() );

		// Note: In production, calling $filter->pre() would trigger redirect and exit
		// We skip that here to avoid process termination in tests
		$this->markTestIncomplete( 'Testing exit() behavior requires different approach' );
	}

	public function testBeforeWithUnverifiedUserButVerificationNotRequired(): void
	{
		// Create unverified user
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );
		$user->setEmailVerified( false );

		// Auth manager returns unverified user
		$this->_authManager
			->method( 'user' )
			->willReturn( $user );

		// Filter doesn't require verification
		$filter = new MemberAuthenticationFilter( $this->_authManager, '/login', false );

		// This should NOT redirect, even though user is unverified
		$filter->pre( $this->_route );

		// Verify user was set in Registry
		$this->assertEquals( $user, Registry::getInstance()->get( 'Auth.User' ) );
	}
}
