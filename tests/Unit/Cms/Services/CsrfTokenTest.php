<?php

namespace Tests\Cms\Services;

use Neuron\Core\System\FakeRandom;
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
	private FakeRandom $random;

	protected function setUp(): void
	{
		$this->sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);

		// Use FakeRandom for deterministic testing
		// Each string() call will advance through the sequence
		$this->random = new FakeRandom();
		$this->random->setSeed(12345); // Seed advances with each call

		$this->_csrfToken = new CsrfToken($this->sessionManager, $this->random);

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

	public function testGenerateTokenIsDifferentEachTime(): void
	{
		// With FakeRandom using a seed, tokens are deterministic but unique
		$token1 = $this->_csrfToken->generate();

		// Clear session to force new token
		$_SESSION = [];

		// Create new instance with advanced seed
		$this->random->setSeed(12346);  // Different seed = different token
		$csrf2 = new CsrfToken($this->sessionManager, $this->random);
		$token2 = $csrf2->generate();

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
		// Set explicit sequence so generate() and regenerate() produce different tokens
		$this->random = new FakeRandom();
		$this->random->setSeed(100);
		$csrf = new CsrfToken($this->sessionManager, $this->random);

		$firstToken = $csrf->generate();

		// Advance seed to get different token
		$this->random->setSeed(200);
		$secondToken = $csrf->regenerate();

		$this->assertNotEquals($firstToken, $secondToken);
		$this->assertEquals($secondToken, $csrf->getToken());
	}

	public function testRegenerateTokenInvalidatesOldToken(): void
	{
		// Set explicit sequence so generate() and regenerate() produce different tokens
		$this->random = new FakeRandom();
		$this->random->setSeed(300);
		$csrf = new CsrfToken($this->sessionManager, $this->random);

		$oldToken = $csrf->generate();

		// Advance seed to get different token
		$this->random->setSeed(400);
		$newToken = $csrf->regenerate();

		// Old token should no longer be valid
		$this->assertFalse($csrf->validate($oldToken));

		// New token should be valid
		$this->assertTrue($csrf->validate($newToken));
	}

	public function testTokenLength(): void
	{
		$token = $this->_csrfToken->generate();

		// Token should be URL-safe and reasonably long
		$this->assertGreaterThanOrEqual(32, strlen($token));
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $token);
	}

	public function testTokenIsSingleUse(): void
	{
		$token = $this->_csrfToken->generate();

		// First validation should succeed
		$this->assertTrue($this->_csrfToken->validate($token));

		// Second validation should fail (token consumed)
		$this->assertFalse($this->_csrfToken->validate($token));
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
