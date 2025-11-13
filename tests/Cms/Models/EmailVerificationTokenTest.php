<?php

namespace Tests\Cms\Models;

use Neuron\Cms\Models\EmailVerificationToken;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class EmailVerificationTokenTest extends TestCase
{
	public function testConstructorSetsDefaultValues(): void
	{
		$userId = 123;
		$token = 'hashed-token';
		$expirationMinutes = 60;

		$verificationToken = new EmailVerificationToken( $userId, $token, $expirationMinutes );

		$this->assertEquals( $userId, $verificationToken->getUserId() );
		$this->assertEquals( $token, $verificationToken->getToken() );
		$this->assertInstanceOf( DateTimeImmutable::class, $verificationToken->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $verificationToken->getExpiresAt() );
	}

	public function testExpirationTimeCalculation(): void
	{
		$userId = 123;
		$token = 'hashed-token';
		$expirationMinutes = 30;

		$verificationToken = new EmailVerificationToken( $userId, $token, $expirationMinutes );

		$createdAt = $verificationToken->getCreatedAt();
		$expiresAt = $verificationToken->getExpiresAt();

		// Calculate the difference in minutes
		$diff = $createdAt->diff( $expiresAt );
		$minutesDiff = ( $diff->h * 60 ) + $diff->i;

		$this->assertEquals( $expirationMinutes, $minutesDiff );
	}

	public function testGettersAndSetters(): void
	{
		$token = new EmailVerificationToken();

		$token->setId( 1 );
		$this->assertEquals( 1, $token->getId() );

		$token->setUserId( 456 );
		$this->assertEquals( 456, $token->getUserId() );

		$token->setToken( 'new-token-hash' );
		$this->assertEquals( 'new-token-hash', $token->getToken() );

		$createdAt = new DateTimeImmutable( '2024-01-01 12:00:00' );
		$token->setCreatedAt( $createdAt );
		$this->assertEquals( $createdAt, $token->getCreatedAt() );

		$expiresAt = new DateTimeImmutable( '2024-01-01 13:00:00' );
		$token->setExpiresAt( $expiresAt );
		$this->assertEquals( $expiresAt, $token->getExpiresAt() );
	}

	public function testIsExpiredReturnsFalseForFutureExpiration(): void
	{
		$userId = 123;
		$token = 'hashed-token';
		$expirationMinutes = 60;

		$verificationToken = new EmailVerificationToken( $userId, $token, $expirationMinutes );

		$this->assertFalse( $verificationToken->isExpired() );
	}

	public function testIsExpiredReturnsTrueForPastExpiration(): void
	{
		$token = new EmailVerificationToken();
		$token->setUserId( 123 );
		$token->setToken( 'hashed-token' );
		$token->setCreatedAt( new DateTimeImmutable( '2020-01-01 12:00:00' ) );
		$token->setExpiresAt( new DateTimeImmutable( '2020-01-01 13:00:00' ) );

		$this->assertTrue( $token->isExpired() );
	}

	public function testToArray(): void
	{
		$userId = 123;
		$tokenHash = 'hashed-token';

		$token = new EmailVerificationToken( $userId, $tokenHash, 60 );
		$token->setId( 1 );

		$array = $token->toArray();

		$this->assertIsArray( $array );
		$this->assertEquals( 1, $array['id'] );
		$this->assertEquals( $userId, $array['user_id'] );
		$this->assertEquals( $tokenHash, $array['token'] );
		$this->assertArrayHasKey( 'created_at', $array );
		$this->assertArrayHasKey( 'expires_at', $array );
	}

	public function testFromArray(): void
	{
		$data = [
			'id' => 1,
			'user_id' => 456,
			'token' => 'test-token-hash',
			'created_at' => '2024-01-01 12:00:00',
			'expires_at' => '2024-01-01 13:00:00'
		];

		$token = EmailVerificationToken::fromArray( $data );

		$this->assertEquals( 1, $token->getId() );
		$this->assertEquals( 456, $token->getUserId() );
		$this->assertEquals( 'test-token-hash', $token->getToken() );
		$this->assertEquals( '2024-01-01 12:00:00', $token->getCreatedAt()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2024-01-01 13:00:00', $token->getExpiresAt()->format( 'Y-m-d H:i:s' ) );
	}

	public function testFromArrayWithPartialData(): void
	{
		$data = [
			'user_id' => 789,
			'token' => 'partial-token-hash'
		];

		$token = EmailVerificationToken::fromArray( $data );

		$this->assertNull( $token->getId() );
		$this->assertEquals( 789, $token->getUserId() );
		$this->assertEquals( 'partial-token-hash', $token->getToken() );
	}
}
