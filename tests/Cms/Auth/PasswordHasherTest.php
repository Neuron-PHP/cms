<?php

namespace Tests\Cms\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Auth\PasswordHasher;

class PasswordHasherTest extends TestCase
{
	private PasswordHasher $hasher;

	protected function setUp(): void
	{
		$this->hasher = new PasswordHasher();
	}

	public function testHashPassword(): void
	{
		$password = 'SecurePassword123!';
		$hash = $this->hasher->hash($password);

		$this->assertNotEquals($password, $hash);
		$this->assertGreaterThan(50, strlen($hash));
	}

	public function testVerifyCorrectPassword(): void
	{
		$password = 'SecurePassword123!';
		$hash = $this->hasher->hash($password);

		$this->assertTrue($this->hasher->verify($password, $hash));
	}

	public function testVerifyIncorrectPassword(): void
	{
		$password = 'SecurePassword123!';
		$wrongPassword = 'WrongPassword123!';
		$hash = $this->hasher->hash($password);

		$this->assertFalse($this->hasher->verify($wrongPassword, $hash));
	}

	public function testPasswordMeetsRequirements(): void
	{
		$this->assertTrue($this->hasher->meetsRequirements('StrongPass1'));
		$this->assertTrue($this->hasher->meetsRequirements('AnotherGood2'));
	}

	public function testPasswordTooShort(): void
	{
		$this->assertFalse($this->hasher->meetsRequirements('Short1'));
	}

	public function testPasswordNoUppercase(): void
	{
		$this->assertFalse($this->hasher->meetsRequirements('nouppercase1'));
	}

	public function testPasswordNoLowercase(): void
	{
		$this->assertFalse($this->hasher->meetsRequirements('NOLOWERCASE1'));
	}

	public function testPasswordNoNumbers(): void
	{
		$this->assertFalse($this->hasher->meetsRequirements('NoNumbers'));
	}

	public function testGetValidationErrors(): void
	{
		$errors = $this->hasher->getValidationErrors('weak');

		$this->assertIsArray($errors);
		$this->assertNotEmpty($errors);
		$this->assertStringContainsString('8 characters', $errors[0]);
	}

	public function testConfigureMinLength(): void
	{
		$this->hasher->setMinLength(12);

		$this->assertFalse($this->hasher->meetsRequirements('Short1Pw'));  // 8 chars
		$this->assertTrue($this->hasher->meetsRequirements('LongerPass12'));  // 12 chars
	}

	public function testConfigureRequireSpecialChars(): void
	{
		$this->hasher->setRequireSpecialChars(true);

		$this->assertFalse($this->hasher->meetsRequirements('NoSpecial1'));
		$this->assertTrue($this->hasher->meetsRequirements('HasSpecial1!'));
	}

	public function testConfigureFromArray(): void
	{
		$this->hasher->configure([
			'min_length' => 10,
			'require_special_chars' => true
		]);

		$this->assertFalse($this->hasher->meetsRequirements('Short1'));
		$this->assertFalse($this->hasher->meetsRequirements('LongerPass1'));
		$this->assertTrue($this->hasher->meetsRequirements('LongerPass1!'));
	}

	public function testNeedsRehash(): void
	{
		$password = 'TestPassword1';
		$hash = $this->hasher->hash($password);

		// A newly created hash should not need rehashing
		$this->assertFalse($this->hasher->needsRehash($hash));
	}

	public function testHashesAreDifferent(): void
	{
		$password = 'SamePassword1';
		$hash1 = $this->hasher->hash($password);
		$hash2 = $this->hasher->hash($password);

		// Same password should produce different hashes (due to salt)
		$this->assertNotEquals($hash1, $hash2);

		// But both should verify correctly
		$this->assertTrue($this->hasher->verify($password, $hash1));
		$this->assertTrue($this->hasher->verify($password, $hash2));
	}
}
