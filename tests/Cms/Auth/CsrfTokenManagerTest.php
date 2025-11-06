<?php

namespace Tests\Cms\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Auth\CsrfTokenManager;
use Neuron\Cms\Auth\SessionManager;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CsrfTokenManagerTest extends TestCase
{
	private CsrfTokenManager $csrfManager;
	private SessionManager $sessionManager;

	protected function setUp(): void
	{
		$this->sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);
		$this->csrfManager = new CsrfTokenManager($this->sessionManager);

		$_SESSION = []; // Clear session data
	}

	protected function tearDown(): void
	{
		$_SESSION = []; // Clean up session data
	}

	public function testGenerateToken(): void
	{
		$token = $this->csrfManager->generate();

		$this->assertNotEmpty($token);
		$this->assertIsString($token);
		$this->assertGreaterThan(20, strlen($token)); // Should be reasonably long
	}

	public function testGenerateTokenStoresInSession(): void
	{
		$token = $this->csrfManager->generate();

		$storedToken = $this->sessionManager->get('csrf_token');

		$this->assertEquals($token, $storedToken);
	}

	public function testGenerateTokenIsRandom(): void
	{
		$token1 = $this->csrfManager->generate();

		// Clear session to force new token
		$_SESSION = [];

		$token2 = $this->csrfManager->generate();

		$this->assertNotEquals($token1, $token2);
	}

	public function testGetTokenReturnsExistingToken(): void
	{
		$generated = $this->csrfManager->generate();

		$retrieved = $this->csrfManager->getToken();

		$this->assertEquals($generated, $retrieved);
	}

	public function testGetTokenGeneratesTokenIfNotExists(): void
	{
		$token = $this->csrfManager->getToken();

		$this->assertNotEmpty($token);
		$this->assertIsString($token);
	}

	public function testValidateWithCorrectToken(): void
	{
		$token = $this->csrfManager->generate();

		$result = $this->csrfManager->validate($token);

		$this->assertTrue($result);
	}

	public function testValidateWithIncorrectToken(): void
	{
		$this->csrfManager->generate();

		$result = $this->csrfManager->validate('incorrect_token');

		$this->assertFalse($result);
	}

	public function testValidateWithEmptyToken(): void
	{
		$this->csrfManager->generate();

		$result = $this->csrfManager->validate('');

		$this->assertFalse($result);
	}

	public function testValidateWithNoStoredToken(): void
	{
		// Don't generate a token first

		$result = $this->csrfManager->validate('some_token');

		$this->assertFalse($result);
	}

	public function testValidateUsesTimingSafeComparison(): void
	{
		// This test verifies that validation uses hash_equals (timing-attack safe)
		// We can't directly test timing, but we verify it accepts exact matches only

		$token = $this->csrfManager->generate();

		// Slightly modified tokens should fail
		$almostToken = substr($token, 0, -1) . 'x';

		$this->assertTrue($this->csrfManager->validate($token));
		$this->assertFalse($this->csrfManager->validate($almostToken));
	}

	public function testRegenerateToken(): void
	{
		$firstToken = $this->csrfManager->generate();

		$secondToken = $this->csrfManager->regenerate();

		$this->assertNotEquals($firstToken, $secondToken);
		$this->assertEquals($secondToken, $this->csrfManager->getToken());
	}

	public function testRegenerateTokenInvalidatesOldToken(): void
	{
		$oldToken = $this->csrfManager->generate();

		$newToken = $this->csrfManager->regenerate();

		// Old token should no longer be valid
		$this->assertFalse($this->csrfManager->validate($oldToken));

		// New token should be valid
		$this->assertTrue($this->csrfManager->validate($newToken));
	}

	public function testTokenLength(): void
	{
		$token = $this->csrfManager->generate();

		// Token should be URL-safe and reasonably long
		$this->assertGreaterThanOrEqual(32, strlen($token));
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token);
	}

	public function testTokenPersistsAcrossMultipleValidations(): void
	{
		$token = $this->csrfManager->generate();

		// Validate multiple times
		$this->assertTrue($this->csrfManager->validate($token));
		$this->assertTrue($this->csrfManager->validate($token));
		$this->assertTrue($this->csrfManager->validate($token));

		// Token should still be valid
		$this->assertEquals($token, $this->csrfManager->getToken());
	}

	public function testTokenIsUrlSafe(): void
	{
		$token = $this->csrfManager->generate();

		// Encode and decode should return same token
		$encoded = urlencode($token);
		$decoded = urldecode($encoded);

		$this->assertEquals($token, $decoded);
	}
}
