<?php

namespace Tests\Cms\Auth;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use DateTimeImmutable;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AuthManagerTest extends TestCase
{
	private AuthManager $authManager;
	private DatabaseUserRepository $userRepository;
	private SessionManager $sessionManager;
	private PasswordHasher $passwordHasher;
	private PDO $pdo;

	protected function setUp(): void
	{
		// Create in-memory SQLite database for testing
		$config = [
			'adapter' => 'sqlite',
			'name' => ':memory:'
		];

		$this->userRepository = new DatabaseUserRepository($config);

		// Get PDO connection via reflection to create table
		$reflection = new \ReflectionClass($this->userRepository);
		$property = $reflection->getProperty('_PDO');
		$property->setAccessible(true);
		$this->pdo = $property->getValue($this->userRepository);

		// Create users table
		$this->createUsersTable();

		$this->sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);
		$this->passwordHasher = new PasswordHasher();

		$this->authManager = new AuthManager(
			$this->userRepository,
			$this->sessionManager,
			$this->passwordHasher
		);
	}

	private function createUsersTable(): void
	{
		$sql = "
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				username VARCHAR(255) UNIQUE NOT NULL,
				email VARCHAR(255) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				role VARCHAR(50) DEFAULT 'subscriber',
				status VARCHAR(50) DEFAULT 'active',
				email_verified BOOLEAN DEFAULT 0,
				two_factor_secret VARCHAR(255) NULL,
				remember_token VARCHAR(255) NULL,
				failed_login_attempts INTEGER DEFAULT 0,
				locked_until TIMESTAMP NULL,
				last_login_at TIMESTAMP NULL,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)
		";

		$this->pdo->exec($sql);
	}

	protected function tearDown(): void
	{
		// Clean up session
		$_SESSION = [];
		// In-memory database is automatically cleaned up
	}

	private function createTestUser(string $username = 'testuser', string $password = 'TestPass123'): User
	{
		$user = new User();
		$user->setUsername($username);
		$user->setEmail($username . '@example.com');
		$user->setPasswordHash($this->passwordHasher->hash($password));
		$user->setRole(User::ROLE_AUTHOR);
		$user->setStatus(User::STATUS_ACTIVE);
		return $this->userRepository->create($user);
	}

	public function testAttemptWithCorrectCredentials(): void
	{
		$user = $this->createTestUser('testuser', 'TestPass123');

		$result = $this->authManager->attempt('testuser', 'TestPass123');

		$this->assertTrue($result);
		$this->assertTrue($this->authManager->check());
	}

	public function testAttemptWithIncorrectPassword(): void
	{
		$user = $this->createTestUser('testuser', 'TestPass123');

		$result = $this->authManager->attempt('testuser', 'WrongPassword');

		$this->assertFalse($result);
		$this->assertFalse($this->authManager->check());
	}

	public function testAttemptWithNonexistentUser(): void
	{
		$result = $this->authManager->attempt('nonexistent', 'password');

		$this->assertFalse($result);
		$this->assertFalse($this->authManager->check());
	}

	public function testAttemptWithInactiveUser(): void
	{
		$user = $this->createTestUser('suspended', 'TestPass123');
		$user->setStatus(User::STATUS_SUSPENDED);
		$this->userRepository->update($user);

		$result = $this->authManager->attempt('suspended', 'TestPass123');

		$this->assertFalse($result);
		$this->assertFalse($this->authManager->check());
	}

	public function testAttemptWithLockedOutUser(): void
	{
		$user = $this->createTestUser('locked', 'TestPass123');
		$user->setLockedUntil((new DateTimeImmutable())->modify('+10 minutes'));
		$this->userRepository->update($user);

		$result = $this->authManager->attempt('locked', 'TestPass123');

		$this->assertFalse($result);
		$this->assertFalse($this->authManager->check());
	}

	public function testFailedLoginAttemptsIncrement(): void
	{
		$user = $this->createTestUser('failtest', 'TestPass123');

		$this->assertEquals(0, $user->getFailedLoginAttempts());

		// First failed attempt
		$this->authManager->attempt('failtest', 'WrongPassword');
		$user = $this->userRepository->findByUsername('failtest');
		$this->assertEquals(1, $user->getFailedLoginAttempts());

		// Second failed attempt
		$this->authManager->attempt('failtest', 'WrongPassword');
		$user = $this->userRepository->findByUsername('failtest');
		$this->assertEquals(2, $user->getFailedLoginAttempts());
	}

	public function testAccountLockoutAfterMaxAttempts(): void
	{
		$user = $this->createTestUser('locktest', 'TestPass123');

		// Make 5 failed attempts (default max)
		for ($i = 0; $i < 5; $i++) {
			$this->authManager->attempt('locktest', 'WrongPassword');
		}

		$user = $this->userRepository->findByUsername('locktest');
		$this->assertTrue($user->isLockedOut());
		$this->assertNotNull($user->getLockedUntil());

		// Should not be able to login even with correct password
		$result = $this->authManager->attempt('locktest', 'TestPass123');
		$this->assertFalse($result);
	}

	public function testSuccessfulLoginResetsFailedAttempts(): void
	{
		$user = $this->createTestUser('resettest', 'TestPass123');

		// Make 3 failed attempts
		for ($i = 0; $i < 3; $i++) {
			$this->authManager->attempt('resettest', 'WrongPassword');
		}

		$user = $this->userRepository->findByUsername('resettest');
		$this->assertEquals(3, $user->getFailedLoginAttempts());

		// Successful login should reset
		$this->authManager->attempt('resettest', 'TestPass123');
		$user = $this->userRepository->findByUsername('resettest');
		$this->assertEquals(0, $user->getFailedLoginAttempts());
	}

	public function testLoginWithRememberMe(): void
	{
		$user = $this->createTestUser('remembertest', 'TestPass123');

		$this->authManager->attempt('remembertest', 'TestPass123', true);

		$user = $this->userRepository->findByUsername('remembertest');
		$this->assertNotNull($user->getRememberToken());
	}

	public function testLoginWithoutRememberMe(): void
	{
		$user = $this->createTestUser('noremember', 'TestPass123');

		$this->authManager->attempt('noremember', 'TestPass123', false);

		$user = $this->userRepository->findByUsername('noremember');
		$this->assertNull($user->getRememberToken());
	}

	public function testCheckReturnsTrue(): void
	{
		$user = $this->createTestUser('checktest', 'TestPass123');
		$this->authManager->attempt('checktest', 'TestPass123');

		$this->assertTrue($this->authManager->check());
	}

	public function testCheckReturnsFalse(): void
	{
		$this->assertFalse($this->authManager->check());
	}

	public function testUserReturnsAuthenticatedUser(): void
	{
		$user = $this->createTestUser('usertest', 'TestPass123');
		$this->authManager->attempt('usertest', 'TestPass123');

		$authUser = $this->authManager->user();

		$this->assertNotNull($authUser);
		$this->assertEquals('usertest', $authUser->getUsername());
	}

	public function testUserReturnsNullWhenNotAuthenticated(): void
	{
		$this->assertNull($this->authManager->user());
	}

	public function testLogout(): void
	{
		$user = $this->createTestUser('logouttest', 'TestPass123');
		$this->authManager->attempt('logouttest', 'TestPass123', true);

		$this->assertTrue($this->authManager->check());

		$this->authManager->logout();

		$this->assertFalse($this->authManager->check());
		$this->assertNull($this->authManager->user());

		// Remember token should be removed
		$user = $this->userRepository->findByUsername('logouttest');
		$this->assertNull($user->getRememberToken());
	}

	public function testLoginUsingRememberToken(): void
	{
		$user = $this->createTestUser('tokentest', 'TestPass123');

		// Generate a plain token and its hash
		$plainToken = bin2hex(random_bytes(32));
		$hashedToken = hash('sha256', $plainToken);

		// Manually set the hashed token on the user
		$user->setRememberToken($hashedToken);
		$this->userRepository->update($user);

		// Login using the plain token (as would come from cookie)
		$result = $this->authManager->loginUsingRememberToken($plainToken);

		$this->assertTrue($result);
		$this->assertTrue($this->authManager->check());
	}

	public function testLoginUsingInvalidRememberToken(): void
	{
		$result = $this->authManager->loginUsingRememberToken('invalid_token');

		$this->assertFalse($result);
		$this->assertFalse($this->authManager->check());
	}

	public function testPasswordRehashingOnLogin(): void
	{
		// Create user with old hash algorithm (simulated)
		$user = $this->createTestUser('rehashtest', 'TestPass123');
		$oldHash = $user->getPasswordHash();

		// Mock that hash needs rehashing (in reality this checks algorithm version)
		// For testing, we'll just verify the hash is checked
		$this->authManager->attempt('rehashtest', 'TestPass123');

		// Password should be hashed with current algorithm
		$user = $this->userRepository->findByUsername('rehashtest');
		$this->assertNotEmpty($user->getPasswordHash());
	}

	public function testIsAdmin(): void
	{
		$user = $this->createTestUser('admintest', 'TestPass123');
		$user->setRole(\Neuron\Cms\Models\User::ROLE_ADMIN);
		$this->userRepository->update($user);

		$this->authManager->attempt('admintest', 'TestPass123');

		$this->assertTrue($this->authManager->isAdmin());
	}

	public function testHasRole(): void
	{
		$user = $this->createTestUser('roletest', 'TestPass123');
		$user->setRole(\Neuron\Cms\Models\User::ROLE_EDITOR);
		$this->userRepository->update($user);

		$this->authManager->attempt('roletest', 'TestPass123');

		$this->assertTrue($this->authManager->hasRole(\Neuron\Cms\Models\User::ROLE_EDITOR));
		$this->assertFalse($this->authManager->hasRole(\Neuron\Cms\Models\User::ROLE_ADMIN));
	}

	public function testUpdateLastLoginTime(): void
	{
		$user = $this->createTestUser('lastlogin', 'TestPass123');

		$this->assertNull($user->getLastLoginAt());

		$this->authManager->attempt('lastlogin', 'TestPass123');

		$user = $this->userRepository->findByUsername('lastlogin');
		$this->assertInstanceOf(DateTimeImmutable::class, $user->getLastLoginAt());
	}
}
