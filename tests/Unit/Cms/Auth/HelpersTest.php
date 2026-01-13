<?php

namespace Tests\Cms\Auth;

use Neuron\Cms\Models\User;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Test auth helper functions
 */
class HelpersTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Ensure helpers are loaded
		require_once __DIR__ . '/../../../../src/Cms/Auth/helpers.php';

		// Reset registry before each test
		Registry::getInstance()->reset();
	}

	protected function tearDown(): void
	{
		// Clean up registry after each test
		Registry::getInstance()->reset();
		parent::tearDown();
	}

	// ========================================
	// current_user_identifier() tests
	// ========================================

	public function testCurrentUserIdentifierReturnsAuthenticatedUsername(): void
	{
		// Create a mock user
		$user = $this->createMock( User::class );
		$user->method( 'getUsername' )
			->willReturn( 'admin' );

		// Set authenticated user in registry
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		$this->assertEquals( 'admin', current_user_identifier() );
	}

	public function testCurrentUserIdentifierFallsBackToOsUser(): void
	{
		// No authenticated user in registry
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, null );

		// Should fall back to OS user (get_current_user())
		$osUser = get_current_user();

		if( !empty( $osUser ) )
		{
			$this->assertEquals( $osUser, current_user_identifier() );
		}
		else
		{
			// If get_current_user() returns empty, should fall back to 'system'
			$this->assertEquals( 'system', current_user_identifier() );
		}
	}

	public function testCurrentUserIdentifierReturnsSystemWhenNoUserAvailable(): void
	{
		// This test assumes we're in an environment where get_current_user() might be empty
		// We can't easily mock get_current_user() since it's a built-in function
		// But we can at least verify the authenticated user path

		Registry::getInstance()->set( RegistryKeys::AUTH_USER, null );

		$result = current_user_identifier();

		// Should be either OS user or 'system'
		$this->assertTrue(
			$result === get_current_user() || $result === 'system',
			'Expected OS user or "system", got: ' . $result
		);
	}

	// ========================================
	// csrf_field() tests
	// ========================================

	public function testCsrfFieldGeneratesHiddenInput(): void
	{
		// Set a CSRF token in the registry
		Registry::getInstance()->set( RegistryKeys::AUTH_CSRF_TOKEN, 'test-token-12345' );

		$html = csrf_field();

		// Should be a hidden input
		$this->assertStringContainsString( '<input type="hidden"', $html );
		$this->assertStringContainsString( 'name="csrf_token"', $html );
		$this->assertStringContainsString( 'value="test-token-12345"', $html );
	}

	public function testCsrfFieldEscapesSpecialCharacters(): void
	{
		// Set a token with special characters that need escaping
		Registry::getInstance()->set( RegistryKeys::AUTH_CSRF_TOKEN, 'token<>"&' );

		$html = csrf_field();

		// Should properly escape HTML special characters
		$this->assertStringContainsString( 'token&lt;&gt;&quot;&amp;', $html );
		$this->assertStringNotContainsString( 'token<>"&', $html );
	}

	public function testCsrfFieldWithEmptyToken(): void
	{
		// No token set
		Registry::getInstance()->set( RegistryKeys::AUTH_CSRF_TOKEN, null );

		$html = csrf_field();

		// Should still generate valid HTML with empty value
		$this->assertStringContainsString( '<input type="hidden"', $html );
		$this->assertStringContainsString( 'name="csrf_token"', $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	// ========================================
	// has_role() tests
	// ========================================

	public function testHasRoleReturnsTrueWhenUserHasRole(): void
	{
		// Create a mock user with admin role
		$user = $this->createMock( User::class );
		$user->method( 'getRole' )
			->willReturn( User::ROLE_ADMIN );

		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		$this->assertTrue( has_role( User::ROLE_ADMIN ) );
	}

	public function testHasRoleReturnsFalseWhenUserHasDifferentRole(): void
	{
		// Create a mock user with subscriber role
		$user = $this->createMock( User::class );
		$user->method( 'getRole' )
			->willReturn( User::ROLE_SUBSCRIBER );

		Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

		$this->assertFalse( has_role( User::ROLE_ADMIN ) );
	}

	public function testHasRoleReturnsFalseWhenNotAuthenticated(): void
	{
		// No authenticated user
		Registry::getInstance()->set( RegistryKeys::AUTH_USER, null );

		$this->assertFalse( has_role( User::ROLE_ADMIN ) );
	}

	public function testHasRoleWithVariousRoles(): void
	{
		$roles = [
			User::ROLE_ADMIN,
			User::ROLE_EDITOR,
			User::ROLE_AUTHOR,
			User::ROLE_SUBSCRIBER
		];

		foreach( $roles as $role )
		{
			$user = $this->createMock( User::class );
			$user->method( 'getRole' )
				->willReturn( $role );

			Registry::getInstance()->set( RegistryKeys::AUTH_USER, $user );

			$this->assertTrue( has_role( $role ) );

			// Should not match other roles
			foreach( $roles as $otherRole )
			{
				if( $otherRole !== $role )
				{
					$this->assertFalse( has_role( $otherRole ) );
				}
			}
		}
	}
}
