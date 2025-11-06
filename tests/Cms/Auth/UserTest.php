<?php

namespace Tests\Cms\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Models\User;
use DateTimeImmutable;

class UserTest extends TestCase
{
	public function testCreateUser(): void
	{
		$user = new User();
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setPasswordHash('hashed_password');

		$this->assertEquals('testuser', $user->getUsername());
		$this->assertEquals('test@example.com', $user->getEmail());
		$this->assertEquals('hashed_password', $user->getPasswordHash());
	}

	public function testDefaultRole(): void
	{
		$user = new User();
		$this->assertEquals(User::ROLE_SUBSCRIBER, $user->getRole());
	}

	public function testDefaultStatus(): void
	{
		$user = new User();
		$this->assertEquals(User::STATUS_ACTIVE, $user->getStatus());
	}

	public function testIsAdmin(): void
	{
		$user = new User();
		$user->setRole(User::ROLE_ADMIN);

		$this->assertTrue($user->isAdmin());
		$this->assertFalse($user->isEditor());
		$this->assertFalse($user->isAuthor());
	}

	public function testIsEditor(): void
	{
		$user = new User();
		$user->setRole(User::ROLE_EDITOR);

		$this->assertFalse($user->isAdmin());
		$this->assertTrue($user->isEditor());
		$this->assertFalse($user->isAuthor());
	}

	public function testIsActive(): void
	{
		$user = new User();
		$user->setStatus(User::STATUS_ACTIVE);

		$this->assertTrue($user->isActive());
		$this->assertFalse($user->isSuspended());
	}

	public function testIsSuspended(): void
	{
		$user = new User();
		$user->setStatus(User::STATUS_SUSPENDED);

		$this->assertFalse($user->isActive());
		$this->assertTrue($user->isSuspended());
	}

	public function testFailedLoginAttempts(): void
	{
		$user = new User();

		$this->assertEquals(0, $user->getFailedLoginAttempts());

		$user->incrementFailedLoginAttempts();
		$this->assertEquals(1, $user->getFailedLoginAttempts());

		$user->incrementFailedLoginAttempts();
		$this->assertEquals(2, $user->getFailedLoginAttempts());

		$user->resetFailedLoginAttempts();
		$this->assertEquals(0, $user->getFailedLoginAttempts());
	}

	public function testIsLockedOut(): void
	{
		$user = new User();

		// Not locked by default
		$this->assertFalse($user->isLockedOut());

		// Lock until future
		$futureTime = (new DateTimeImmutable())->modify('+10 minutes');
		$user->setLockedUntil($futureTime);
		$this->assertTrue($user->isLockedOut());

		// Lock until past (expired)
		$pastTime = (new DateTimeImmutable())->modify('-10 minutes');
		$user->setLockedUntil($pastTime);
		$this->assertFalse($user->isLockedOut());
	}

	public function testResetFailedLoginAttemptsRemovesLockout(): void
	{
		$user = new User();
		$user->setFailedLoginAttempts(5);
		$user->setLockedUntil((new DateTimeImmutable())->modify('+10 minutes'));

		$this->assertTrue($user->isLockedOut());

		$user->resetFailedLoginAttempts();

		$this->assertEquals(0, $user->getFailedLoginAttempts());
		$this->assertFalse($user->isLockedOut());
	}

	public function testHasTwoFactorEnabled(): void
	{
		$user = new User();

		$this->assertFalse($user->hasTwoFactorEnabled());

		$user->setTwoFactorSecret('secret_key');
		$this->assertTrue($user->hasTwoFactorEnabled());
	}

	public function testToArray(): void
	{
		$user = new User();
		$user->setId(1);
		$user->setUsername('testuser');
		$user->setEmail('test@example.com');
		$user->setPasswordHash('hash');
		$user->setRole(User::ROLE_ADMIN);

		$array = $user->toArray();

		$this->assertIsArray($array);
		$this->assertEquals(1, $array['id']);
		$this->assertEquals('testuser', $array['username']);
		$this->assertEquals('test@example.com', $array['email']);
		$this->assertEquals(User::ROLE_ADMIN, $array['role']);
	}

	public function testFromArray(): void
	{
		$data = [
			'id' => 1,
			'username' => 'testuser',
			'email' => 'test@example.com',
			'password_hash' => 'hash',
			'role' => User::ROLE_EDITOR,
			'status' => User::STATUS_ACTIVE,
			'email_verified' => true,
			'failed_login_attempts' => 3
		];

		$user = User::fromArray($data);

		$this->assertEquals(1, $user->getId());
		$this->assertEquals('testuser', $user->getUsername());
		$this->assertEquals('test@example.com', $user->getEmail());
		$this->assertEquals(User::ROLE_EDITOR, $user->getRole());
		$this->assertEquals(User::STATUS_ACTIVE, $user->getStatus());
		$this->assertTrue($user->isEmailVerified());
		$this->assertEquals(3, $user->getFailedLoginAttempts());
	}

	public function testCreatedAtSetOnConstruct(): void
	{
		$user = new User();

		$this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
	}
}
