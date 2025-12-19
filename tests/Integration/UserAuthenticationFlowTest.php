<?php

namespace Tests\Integration;

use DateTimeImmutable;

/**
 * Integration test for user authentication flow.
 *
 * Tests complete user lifecycle:
 * - User registration with password hashing
 * - Email verification tokens
 * - Login authentication
 * - Password reset flow
 * - Session management
 *
 * Uses real database with actual migrations.
 *
 * @package Tests\Integration
 */
class UserAuthenticationFlowTest extends IntegrationTestCase
{
	/**
	 * Test complete user registration flow
	 */
	public function testUserRegistrationFlow(): void
	{
		// 1. Register new user
		$passwordHash = password_hash( 'SecurePassword123!', PASSWORD_DEFAULT );

		$stmt = $this->pdo->prepare(
			"INSERT INTO users (username, email, password_hash, role, status, email_verified, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute([
			'newuser',
			'newuser@example.com',
			$passwordHash,
			'subscriber',
			'active',
			0, // Not verified yet
			$now,
			$now
		]);

		$userId = (int)$this->pdo->lastInsertId();
		$this->assertGreaterThan( 0, $userId );

		// 2. Verify user was created with correct data
		$stmt = $this->pdo->prepare( "SELECT * FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );
		$user = $stmt->fetch();

		$this->assertEquals( 'newuser', $user['username'] );
		$this->assertEquals( 'newuser@example.com', $user['email'] );
		$this->assertEquals( 'subscriber', $user['role'] );
		$this->assertEquals( 'active', $user['status'] );
		$this->assertEquals( 0, $user['email_verified'] );

		// 3. Verify password hash works
		$this->assertTrue( password_verify( 'SecurePassword123!', $user['password_hash'] ) );
		$this->assertFalse( password_verify( 'WrongPassword', $user['password_hash'] ) );
	}

	/**
	 * Test email verification token flow
	 */
	public function testEmailVerificationTokenFlow(): void
	{
		// 1. Create unverified user
		$userId = $this->createTestUser([
			'username' => 'unverified',
			'email' => 'unverified@example.com',
			'email_verified' => 0
		]);

		// 2. Generate verification token
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$expiresAt = (new DateTimeImmutable())->modify( '+60 minutes' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$userId,
			$hashedToken,
			date( 'Y-m-d H:i:s' ),
			$expiresAt->format( 'Y-m-d H:i:s' )
		]);

		$tokenId = (int)$this->pdo->lastInsertId();
		$this->assertGreaterThan( 0, $tokenId );

		// 3. Verify token exists
		$stmt = $this->pdo->prepare(
			"SELECT * FROM email_verification_tokens WHERE user_id = ? AND token = ?"
		);
		$stmt->execute( [$userId, $hashedToken] );
		$token = $stmt->fetch();

		$this->assertNotFalse( $token );
		$this->assertEquals( $userId, $token['user_id'] );
		$this->assertEquals( $hashedToken, $token['token'] );

		// 4. Verify the user's email
		$stmt = $this->pdo->prepare( "UPDATE users SET email_verified = 1 WHERE id = ?" );
		$stmt->execute( [$userId] );

		// 5. Delete used token
		$stmt = $this->pdo->prepare( "DELETE FROM email_verification_tokens WHERE id = ?" );
		$stmt->execute( [$tokenId] );

		// 6. Verify user is now verified
		$stmt = $this->pdo->prepare( "SELECT email_verified FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );
		$verified = (int)$stmt->fetchColumn();

		$this->assertEquals( 1, $verified );

		// 7. Verify token was deleted
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM email_verification_tokens WHERE id = ?" );
		$stmt->execute( [$tokenId] );
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test password reset token flow
	 */
	public function testPasswordResetTokenFlow(): void
	{
		// 1. Create user
		$userId = $this->createTestUser([
			'username' => 'resetuser',
			'email' => 'reset@example.com'
		]);

		// 2. Request password reset - generate token
		$plainToken = bin2hex( random_bytes( 32 ) );
		$hashedToken = hash( 'sha256', $plainToken );
		$expiresAt = (new DateTimeImmutable())->modify( '+60 minutes' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO password_reset_tokens (email, token, created_at, expires_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			'reset@example.com',
			$hashedToken,
			date( 'Y-m-d H:i:s' ),
			$expiresAt->format( 'Y-m-d H:i:s' )
		]);

		$tokenId = (int)$this->pdo->lastInsertId();

		// 3. Validate token exists
		$stmt = $this->pdo->prepare(
			"SELECT * FROM password_reset_tokens WHERE email = ? AND token = ?"
		);
		$stmt->execute( ['reset@example.com', $hashedToken] );
		$token = $stmt->fetch();

		$this->assertNotFalse( $token );
		$this->assertEquals( 'reset@example.com', $token['email'] );

		// 4. Reset password
		$newPasswordHash = password_hash( 'NewSecurePassword456!', PASSWORD_DEFAULT );

		$stmt = $this->pdo->prepare(
			"UPDATE users SET password_hash = ?, updated_at = ? WHERE email = ?"
		);
		$stmt->execute([
			$newPasswordHash,
			date( 'Y-m-d H:i:s' ),
			'reset@example.com'
		]);

		// 5. Delete used token
		$stmt = $this->pdo->prepare( "DELETE FROM password_reset_tokens WHERE id = ?" );
		$stmt->execute( [$tokenId] );

		// 6. Verify password was changed
		$stmt = $this->pdo->prepare( "SELECT password_hash FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );
		$updatedHash = $stmt->fetchColumn();

		$this->assertTrue( password_verify( 'NewSecurePassword456!', $updatedHash ) );
		$this->assertFalse( password_verify( 'password', $updatedHash ) ); // Old password doesn't work

		// 7. Verify token was deleted
		$stmt = $this->pdo->prepare( "SELECT COUNT(*) FROM password_reset_tokens WHERE id = ?" );
		$stmt->execute( [$tokenId] );
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test expired token cleanup
	 */
	public function testExpiredTokenCleanup(): void
	{
		$userId = $this->createTestUser([
			'username' => 'tokenuser',
			'email' => 'token@example.com'
		]);

		// Create expired email verification token
		$expiredTime = (new DateTimeImmutable())->modify( '-1 hour' );

		$stmt = $this->pdo->prepare(
			"INSERT INTO email_verification_tokens (user_id, token, created_at, expires_at)
			VALUES (?, ?, ?, ?)"
		);

		$stmt->execute([
			$userId,
			hash( 'sha256', 'expired_token' ),
			$expiredTime->modify( '-60 minutes' )->format( 'Y-m-d H:i:s' ),
			$expiredTime->format( 'Y-m-d H:i:s' )
		]);

		// Create valid token
		$validTime = (new DateTimeImmutable())->modify( '+1 hour' );

		$stmt->execute([
			$userId,
			hash( 'sha256', 'valid_token' ),
			date( 'Y-m-d H:i:s' ),
			$validTime->format( 'Y-m-d H:i:s' )
		]);

		// Clean up expired tokens
		$stmt = $this->pdo->prepare(
			"DELETE FROM email_verification_tokens WHERE expires_at < ?"
		);
		$stmt->execute( [date( 'Y-m-d H:i:s' )] );

		// Verify only valid token remains
		$stmt = $this->pdo->prepare(
			"SELECT COUNT(*) FROM email_verification_tokens WHERE user_id = ?"
		);
		$stmt->execute( [$userId] );
		$count = (int)$stmt->fetchColumn();

		$this->assertEquals( 1, $count, 'Only valid token should remain after cleanup' );
	}

	/**
	 * Test user login attempt tracking
	 */
	public function testLoginAttemptTracking(): void
	{
		$userId = $this->createTestUser([
			'username' => 'loginuser',
			'email' => 'login@example.com',
			'failed_login_attempts' => 0
		]);

		// Simulate 3 failed login attempts
		for( $i = 1; $i <= 3; $i++ )
		{
			$stmt = $this->pdo->prepare(
				"UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?"
			);
			$stmt->execute( [$userId] );

			// Verify count incremented
			$stmt = $this->pdo->prepare( "SELECT failed_login_attempts FROM users WHERE id = ?" );
			$stmt->execute( [$userId] );
			$attempts = (int)$stmt->fetchColumn();

			$this->assertEquals( $i, $attempts );
		}

		// Lock account after 3 failed attempts
		$lockedUntil = (new DateTimeImmutable())->modify( '+30 minutes' );

		$stmt = $this->pdo->prepare(
			"UPDATE users SET locked_until = ? WHERE id = ?"
		);
		$stmt->execute([
			$lockedUntil->format( 'Y-m-d H:i:s' ),
			$userId
		]);

		// Verify account is locked
		$stmt = $this->pdo->prepare( "SELECT locked_until FROM users WHERE id = ?" );
		$stmt->execute( [$userId] );
		$locked = $stmt->fetchColumn();

		$this->assertNotNull( $locked );
		$this->assertGreaterThan( date( 'Y-m-d H:i:s' ), $locked );

		// Simulate successful login - reset attempts
		$stmt = $this->pdo->prepare(
			"UPDATE users SET
				failed_login_attempts = 0,
				locked_until = NULL,
				last_login_at = ?
			WHERE id = ?"
		);
		$stmt->execute([
			date( 'Y-m-d H:i:s' ),
			$userId
		]);

		// Verify reset
		$stmt = $this->pdo->prepare(
			"SELECT failed_login_attempts, locked_until FROM users WHERE id = ?"
		);
		$stmt->execute( [$userId] );
		$user = $stmt->fetch();

		$this->assertEquals( 0, $user['failed_login_attempts'] );
		$this->assertNull( $user['locked_until'] );
	}

	/**
	 * Test username and email uniqueness constraints
	 */
	public function testUsernameEmailUniqueness(): void
	{
		// Create first user
		$this->createTestUser([
			'username' => 'unique_user',
			'email' => 'unique@example.com'
		]);

		// Try to create user with duplicate username
		$this->expectException( \PDOException::class );

		$stmt = $this->pdo->prepare(
			"INSERT INTO users (username, email, password_hash, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute([
			'unique_user', // Duplicate username
			'different@example.com',
			password_hash( 'password', PASSWORD_DEFAULT ),
			$now,
			$now
		]);
	}

	/**
	 * Test email uniqueness constraint
	 */
	public function testEmailUniquenessConstraint(): void
	{
		// Create first user
		$this->createTestUser([
			'username' => 'user1',
			'email' => 'same@example.com'
		]);

		// Try to create user with duplicate email
		$this->expectException( \PDOException::class );

		$stmt = $this->pdo->prepare(
			"INSERT INTO users (username, email, password_hash, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?)"
		);

		$now = date( 'Y-m-d H:i:s' );
		$stmt->execute([
			'user2', // Different username
			'same@example.com', // Duplicate email
			password_hash( 'password', PASSWORD_DEFAULT ),
			$now,
			$now
		]);
	}

	/**
	 * Test user roles and status
	 */
	public function testUserRolesAndStatus(): void
	{
		$roles = ['subscriber', 'contributor', 'author', 'editor', 'administrator'];
		$statuses = ['active', 'inactive', 'suspended'];

		foreach( $roles as $role )
		{
			$userId = $this->createTestUser([
				'username' => "user_role_{$role}",
				'email' => "{$role}@example.com",
				'role' => $role
			]);

			$stmt = $this->pdo->prepare( "SELECT role FROM users WHERE id = ?" );
			$stmt->execute( [$userId] );
			$savedRole = $stmt->fetchColumn();

			$this->assertEquals( $role, $savedRole );
		}

		foreach( $statuses as $status )
		{
			$userId = $this->createTestUser([
				'username' => "user_status_{$status}",
				'email' => "{$status}@example.com",
				'status' => $status
			]);

			$stmt = $this->pdo->prepare( "SELECT status FROM users WHERE id = ?" );
			$stmt->execute( [$userId] );
			$savedStatus = $stmt->fetchColumn();

			$this->assertEquals( $status, $savedStatus );
		}
	}
}
