<?php

namespace Tests\Cms\Services;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Auth\SessionManager;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CsrfTokenTest extends TestCase
{
	private CsrfToken $_csrfToken;
	private SessionManager $sessionManager;

	protected function setUp(): void
	{
		$this->sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);
		$this->_csrfToken = new CsrfToken($this->sessionManager);

		$_SESSION = []; // Clear session data
	}

	protected function tearDown(): void
	{
		$_SESSION = []; // Clean up session data
	}

	public function testGenerateToken(): void
	{
		$token = $this->_csrfToken->generate();

		$this->assertNotEmpty($token);
		$this->assertIsString($token);
		$this->assertGreaterThan(20, strlen($token)); // Should be reasonably long
	}

	public function testGenerateTokenStoresInSession(): void
	{
		$token = $this->_csrfToken->generate();

		$storedToken = $this->sessionManager->get('csrf_token');

		$this->assertEquals($token, $storedToken);
	}

	public function testGenerateTokenIsRandom(): void
	{
		$token1 = $this->_csrfToken->generate();

		// Clear session to force new token
		$_SESSION = [];

		$token2 = $this->_csrfToken->generate();

		$this->assertNotEquals($token1, $token2);
	}

	public function testGetTokenReturnsExistingToken(): void
	{
		$generated = $this->_csrfToken->generate();

		$retrieved = $this->_csrfToken->getToken();

		$this->assertEquals($generated, $retrieved);
	}

	public function testGetTokenGeneratesTokenIfNotExists(): void
	{
		$token = $this->_csrfToken->getToken();

		$this->assertNotEmpty($token);
		$this->assertIsString($token);
	}

	public function testValidateWithCorrectToken(): void
	{
		$token = $this->_csrfToken->generate();

		$result = $this->_csrfToken->validate($token);

		$this->assertTrue($result);
	}

	public function testValidateWithIncorrectToken(): void
	{
		$this->_csrfToken->generate();

		$result = $this->_csrfToken->validate('incorrect_token');

		$this->assertFalse($result);
	}

	public function testValidateWithEmptyToken(): void
	{
		$this->_csrfToken->generate();

		$result = $this->_csrfToken->validate('');

		$this->assertFalse($result);
	}

	public function testValidateWithNoStoredToken(): void
	{
		// Don't generate a token first

		$result = $this->_csrfToken->validate('some_token');

		$this->assertFalse($result);
	}

	public function testValidateUsesTimingSafeComparison(): void
	{
		// This test verifies that validation uses hash_equals (timing-attack safe)
		// We can't directly test timing, but we verify it accepts exact matches only

		$token = $this->_csrfToken->generate();

		// Slightly modified tokens should fail
		$almostToken = substr($token, 0, -1) . 'x';

		$this->assertTrue($this->_csrfToken->validate($token));
		$this->assertFalse($this->_csrfToken->validate($almostToken));
	}

	public function testRegenerateToken(): void
	{
		$firstToken = $this->_csrfToken->generate();

		$secondToken = $this->_csrfToken->regenerate();

		$this->assertNotEquals($firstToken, $secondToken);
		$this->assertEquals($secondToken, $this->_csrfToken->getToken());
	}

	public function testRegenerateTokenInvalidatesOldToken(): void
	{
		$oldToken = $this->_csrfToken->generate();

		$newToken = $this->_csrfToken->regenerate();

		// Old token should no longer be valid
		$this->assertFalse($this->_csrfToken->validate($oldToken));

		// New token should be valid
		$this->assertTrue($this->_csrfToken->validate($newToken));
	}

	public function testTokenLength(): void
	{
		$token = $this->_csrfToken->generate();

		// Token should be URL-safe and reasonably long
		$this->assertGreaterThanOrEqual(32, strlen($token));
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token);
	}

	public function testTokenPersistsAcrossMultipleValidations(): void
	{
		$token = $this->_csrfToken->generate();

		// Validate multiple times
		$this->assertTrue($this->_csrfToken->validate($token));
		$this->assertTrue($this->_csrfToken->validate($token));
		$this->assertTrue($this->_csrfToken->validate($token));

		// Token should still be valid
		$this->assertEquals($token, $this->_csrfToken->getToken());
	}

	public function testTokenIsUrlSafe(): void
	{
		$token = $this->_csrfToken->generate();

		// Encode and decode should return same token
		$encoded = urlencode($token);
		$decoded = urldecode($encoded);

		$this->assertEquals($token, $decoded);
	}
}
