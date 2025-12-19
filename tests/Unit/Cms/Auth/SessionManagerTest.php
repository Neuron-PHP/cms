<?php

namespace Tests\Cms\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Auth\SessionManager;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionManagerTest extends TestCase
{
	private SessionManager $sessionManager;

	protected function setUp(): void
	{
		$this->sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);
		$_SESSION = []; // Clear session data
	}

	protected function tearDown(): void
	{
		$_SESSION = []; // Clean up session data
	}

	public function testSetAndGet(): void
	{
		$this->sessionManager->set('test_key', 'test_value');

		$this->assertEquals('test_value', $this->sessionManager->get('test_key'));
	}

	public function testGetWithDefault(): void
	{
		$value = $this->sessionManager->get('nonexistent', 'default_value');

		$this->assertEquals('default_value', $value);
	}

	public function testGetReturnsNullForNonexistent(): void
	{
		$value = $this->sessionManager->get('nonexistent');

		$this->assertNull($value);
	}

	public function testHasReturnsTrueForExisting(): void
	{
		$this->sessionManager->set('exists', 'value');

		$this->assertTrue($this->sessionManager->has('exists'));
	}

	public function testHasReturnsFalseForNonexistent(): void
	{
		$this->assertFalse($this->sessionManager->has('nonexistent'));
	}

	public function testRemove(): void
	{
		$this->sessionManager->set('to_remove', 'value');
		$this->assertTrue($this->sessionManager->has('to_remove'));

		$this->sessionManager->remove('to_remove');

		$this->assertFalse($this->sessionManager->has('to_remove'));
	}

	public function testFlashMessage(): void
	{
		$this->sessionManager->flash('success', 'Operation completed');

		// Flash message should be stored in _flash array
		$this->assertTrue($this->sessionManager->has('_flash'));
		$flash = $this->sessionManager->get('_flash');
		$this->assertEquals('Operation completed', $flash['success']);
	}

	public function testGetFlashReturnsMessage(): void
	{
		$this->sessionManager->flash('info', 'Information message');

		$message = $this->sessionManager->getFlash('info');

		$this->assertEquals('Information message', $message);
	}

	public function testGetFlashRemovesMessage(): void
	{
		$this->sessionManager->flash('info', 'Information message');

		$this->sessionManager->getFlash('info');

		// Second call should return null
		$this->assertNull($this->sessionManager->getFlash('info'));
	}

	public function testGetFlashReturnsDefaultForNonexistent(): void
	{
		$message = $this->sessionManager->getFlash('nonexistent', 'default');

		$this->assertEquals('default', $message);
	}

	public function testHasFlashReturnsTrueForExisting(): void
	{
		$this->sessionManager->flash('warning', 'Warning message');

		$this->assertTrue($this->sessionManager->hasFlash('warning'));
	}

	public function testHasFlashReturnsFalseForNonexistent(): void
	{
		$this->assertFalse($this->sessionManager->hasFlash('nonexistent'));
	}

	public function testGetAllFlashReturnsAllMessages(): void
	{
		$this->sessionManager->flash('success', 'Success message');
		$this->sessionManager->flash('error', 'Error message');

		$all = $this->sessionManager->getAllFlash();

		$this->assertIsArray($all);
		$this->assertEquals('Success message', $all['success']);
		$this->assertEquals('Error message', $all['error']);
	}

	public function testGetAllFlashClearsMessages(): void
	{
		$this->sessionManager->flash('info', 'Info message');

		$this->sessionManager->getAllFlash();

		// Should be cleared after retrieval
		$this->assertFalse($this->sessionManager->hasFlash('info'));
	}

	public function testRegenerateChangesSessionId(): void
	{
		$this->sessionManager->start();
		$oldId = session_id();

		$result = $this->sessionManager->regenerate();

		$newId = session_id();

		$this->assertTrue($result);
		$this->assertNotEquals($oldId, $newId);
	}

	public function testRegeneratePreservesSessionData(): void
	{
		$this->sessionManager->set('preserved', 'data');

		$this->sessionManager->regenerate();

		$this->assertEquals('data', $this->sessionManager->get('preserved'));
	}

	public function testGetId(): void
	{
		$sessionId = $this->sessionManager->getId();

		$this->assertNotEmpty($sessionId);
		$this->assertEquals(session_id(), $sessionId);
	}

	public function testIsStarted(): void
	{
		$this->assertFalse($this->sessionManager->isStarted());

		$this->sessionManager->start();

		$this->assertTrue($this->sessionManager->isStarted());
	}

	public function testMultipleFlashMessages(): void
	{
		$this->sessionManager->flash('success', 'Success message');
		$this->sessionManager->flash('error', 'Error message');
		$this->sessionManager->flash('info', 'Info message');

		$this->assertTrue($this->sessionManager->hasFlash('success'));
		$this->assertTrue($this->sessionManager->hasFlash('error'));
		$this->assertTrue($this->sessionManager->hasFlash('info'));

		$this->assertEquals('Success message', $this->sessionManager->getFlash('success'));
		$this->assertEquals('Error message', $this->sessionManager->getFlash('error'));
		$this->assertEquals('Info message', $this->sessionManager->getFlash('info'));
	}

	public function testDestroyRemovesAllSessionData(): void
	{
		$this->sessionManager->set('test', 'value');
		$this->sessionManager->flash('message', 'flash value');

		$result = $this->sessionManager->destroy();

		$this->assertTrue($result);
		// After destroy, session should be empty
		$this->assertEmpty($_SESSION);
	}
}
