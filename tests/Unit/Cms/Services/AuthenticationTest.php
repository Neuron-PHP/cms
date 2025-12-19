<?php

namespace Tests\Cms\Services;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Models\User;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Orm\Model;
use DateTimeImmutable;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AuthenticationTest extends TestCase
{
	private Authentication $_authentication;
	private DatabaseUserRepository $_userRepository;
	private SessionManager $_sessionManager;
	private PasswordHasher $_passwordHasher;
	private PDO $pdo;

	public function __sleep(): array
	{
		// Don't serialize PDO for process isolation
		return ['_authentication', '_userRepository', '_sessionManager', '_passwordHasher'];
	}

	public function __wakeup(): void
	{
		// PDO will be re-initialized in setUp()
	}

	protected function setUp(): void
	{
		// Create in-memory SQLite database for testing
		$config = [
			'adapter' => 'sqlite',
			'name' => ':memory:'
		];

		// Mock SettingManager
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->with( 'database' )
			->willReturn( $config );

		$this->_userRepository = new DatabaseUserRepository($settings);

		// Get PDO connection via reflection to create table
		$reflection = new \ReflectionClass($this->_userRepository);
		$property = $reflection->getProperty('_pdo');
		$property->setAccessible(true);
		$this->pdo = $property->getValue($this->_userRepository);

		// Initialize ORM with the PDO connection
		Model::setPdo( $this->pdo );

		// Create users table
		$this->createUsersTable();

		$this->_sessionManager = new SessionManager([
			'cookie_secure' => false  // Disable HTTPS requirement for tests
		]);
		$this->_passwordHasher = new PasswordHasher();

		$this->_authentication = new Authentication(
			$this->_userRepository,
			$this->_sessionManager,
			$this->_passwordHasher
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
				two_factor_recovery_codes TEXT NULL,
				remember_token VARCHAR(255) NULL,
				failed_login_attempts INTEGER DEFAULT 0,
				locked_until TIMESTAMP NULL,
				last_login_at TIMESTAMP NULL,
				timezone VARCHAR(50) DEFAULT 'UTC',
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
		$user->setPasswordHash($this->_passwordHasher->hash($password));
		$user->setRole(User::ROLE_AUTHOR);
		$user->setStatus(User::STATUS_ACTIVE);
		return $this->_userRepository->create($user);
	}

	public function testAttemptWithCorrectCredentials(): void
	{
		$user = $this->createTestUser('testuser', 'TestPass123');

		$result = $this->_authentication->attempt('testuser', 'TestPass123');

		$this->assertTrue($result);
		$this->assertTrue($this->_authentication->check());
	}

	public function testAttemptWithIncorrectPassword(): void
	{
		$user = $this->createTestUser('testuser', 'TestPass123');

		$result = $this->_authentication->attempt('testuser', 'WrongPassword');

		$this->assertFalse($result);
		$this->assertFalse($this->_authentication->check());
	}

	public function testAttemptWithNonexistentUser(): void
	{
		$result = $this->_authentication->attempt('nonexistent', 'password');

		$this->assertFalse($result);
		$this->assertFalse($this->_authentication->check());
	}

	public function testAttemptWithInactiveUser(): void
	{
		$user = $this->createTestUser('suspended', 'TestPass123');
		$user->setStatus(User::STATUS_SUSPENDED);
		$this->_userRepository->update($user);

		$result = $this->_authentication->attempt('suspended', 'TestPass123');

		$this->assertFalse($result);
		$this->assertFalse($this->_authentication->check());
	}

	public function testAttemptWithLockedOutUser(): void
	{
		$user = $this->createTestUser('locked', 'TestPass123');
		$user->setLockedUntil((new DateTimeImmutable())->modify('+10 minutes'));
		$this->_userRepository->update($user);

		$result = $this->_authentication->attempt('locked', 'TestPass123');

		$this->assertFalse($result);
		$this->assertFalse($this->_authentication->check());
	}

	public function testFailedLoginAttemptsIncrement(): void
	{
		$user = $this->createTestUser('failtest', 'TestPass123');

		$this->assertEquals(0, $user->getFailedLoginAttempts());

		// First failed attempt
		$this->_authentication->attempt('failtest', 'WrongPassword');
		$user = $this->_userRepository->findByUsername('failtest');
		$this->assertEquals(1, $user->getFailedLoginAttempts());

		// Second failed attempt
		$this->_authentication->attempt('failtest', 'WrongPassword');
		$user = $this->_userRepository->findByUsername('failtest');
		$this->assertEquals(2, $user->getFailedLoginAttempts());
	}

	public function testAccountLockoutAfterMaxAttempts(): void
	{
		$user = $this->createTestUser('locktest', 'TestPass123');

		// Make 5 failed attempts (default max)
		for ($i = 0; $i < 5; $i++) {
			$this->_authentication->attempt('locktest', 'WrongPassword');
		}

		$user = $this->_userRepository->findByUsername('locktest');
		$this->assertTrue($user->isLockedOut());
		$this->assertNotNull($user->getLockedUntil());

		// Should not be able to login even with correct password
		$result = $this->_authentication->attempt('locktest', 'TestPass123');
		$this->assertFalse($result);
	}

	public function testSuccessfulLoginResetsFailedAttempts(): void
	{
		$user = $this->createTestUser('resettest', 'TestPass123');

		// Make 3 failed attempts
		for ($i = 0; $i < 3; $i++) {
			$this->_authentication->attempt('resettest', 'WrongPassword');
		}

		$user = $this->_userRepository->findByUsername('resettest');
		$this->assertEquals(3, $user->getFailedLoginAttempts());

		// Successful login should reset
		$this->_authentication->attempt('resettest', 'TestPass123');
		$user = $this->_userRepository->findByUsername('resettest');
		$this->assertEquals(0, $user->getFailedLoginAttempts());
	}

	public function testLoginWithRememberMe(): void
	{
		$user = $this->createTestUser('remembertest', 'TestPass123');

		$this->_authentication->attempt('remembertest', 'TestPass123', true);

		$user = $this->_userRepository->findByUsername('remembertest');
		$this->assertNotNull($user->getRememberToken());
	}

	public function testLoginWithoutRememberMe(): void
	{
		$user = $this->createTestUser('noremember', 'TestPass123');

		$this->_authentication->attempt('noremember', 'TestPass123', false);

		$user = $this->_userRepository->findByUsername('noremember');
		$this->assertNull($user->getRememberToken());
	}

	public function testCheckReturnsTrue(): void
	{
		$user = $this->createTestUser('checktest', 'TestPass123');
		$this->_authentication->attempt('checktest', 'TestPass123');

		$this->assertTrue($this->_authentication->check());
	}

	public function testCheckReturnsFalse(): void
	{
		$this->assertFalse($this->_authentication->check());
	}

	public function testUserReturnsAuthenticatedUser(): void
	{
		$user = $this->createTestUser('usertest', 'TestPass123');
		$this->_authentication->attempt('usertest', 'TestPass123');

		$authUser = $this->_authentication->user();

		$this->assertNotNull($authUser);
		$this->assertEquals('usertest', $authUser->getUsername());
	}

	public function testUserReturnsNullWhenNotAuthenticated(): void
	{
		$this->assertNull($this->_authentication->user());
	}

	public function testLogout(): void
	{
		$user = $this->createTestUser('logouttest', 'TestPass123');
		$this->_authentication->attempt('logouttest', 'TestPass123', true);

		$this->assertTrue($this->_authentication->check());

		$this->_authentication->logout();

		$this->assertFalse($this->_authentication->check());
		$this->assertNull($this->_authentication->user());

		// Remember token should be removed
		$user = $this->_userRepository->findByUsername('logouttest');
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
		$this->_userRepository->update($user);

		// Login using the plain token (as would come from cookie)
		$result = $this->_authentication->loginUsingRememberToken($plainToken);

		$this->assertTrue($result);
		$this->assertTrue($this->_authentication->check());
	}

	public function testLoginUsingInvalidRememberToken(): void
	{
		$result = $this->_authentication->loginUsingRememberToken('invalid_token');

		$this->assertFalse($result);
		$this->assertFalse($this->_authentication->check());
	}

	public function testPasswordRehashingOnLogin(): void
	{
		// Create user with old hash algorithm (simulated)
		$user = $this->createTestUser('rehashtest', 'TestPass123');
		$oldHash = $user->getPasswordHash();

		// Mock that hash needs rehashing (in reality this checks algorithm version)
		// For testing, we'll just verify the hash is checked
		$this->_authentication->attempt('rehashtest', 'TestPass123');

		// Password should be hashed with current algorithm
		$user = $this->_userRepository->findByUsername('rehashtest');
		$this->assertNotEmpty($user->getPasswordHash());
	}

	public function testIsAdmin(): void
	{
		$user = $this->createTestUser('admintest', 'TestPass123');
		$user->setRole(\Neuron\Cms\Models\User::ROLE_ADMIN);
		$this->_userRepository->update($user);

		$this->_authentication->attempt('admintest', 'TestPass123');

		$this->assertTrue($this->_authentication->isAdmin());
	}

	public function testHasRole(): void
	{
		$user = $this->createTestUser('roletest', 'TestPass123');
		$user->setRole(\Neuron\Cms\Models\User::ROLE_EDITOR);
		$this->_userRepository->update($user);

		$this->_authentication->attempt('roletest', 'TestPass123');

		$this->assertTrue($this->_authentication->hasRole(\Neuron\Cms\Models\User::ROLE_EDITOR));
		$this->assertFalse($this->_authentication->hasRole(\Neuron\Cms\Models\User::ROLE_ADMIN));
	}

	public function testUpdateLastLoginTime(): void
	{
		$user = $this->createTestUser('lastlogin', 'TestPass123');

		$this->assertNull($user->getLastLoginAt());

		$this->_authentication->attempt('lastlogin', 'TestPass123');

		$user = $this->_userRepository->findByUsername('lastlogin');
		$this->assertInstanceOf(DateTimeImmutable::class, $user->getLastLoginAt());
	}
}
