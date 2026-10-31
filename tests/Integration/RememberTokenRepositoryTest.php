<?php

namespace Tests\Integration;

use DateTimeImmutable;
use Neuron\Cms\Models\RememberToken;
use Neuron\Cms\Repositories\DatabaseRememberTokenRepository;
use Neuron\Data\Settings\SettingManager;

/**
 * Remember token repository against the migrated remember_tokens table.
 *
 * @package Tests\Integration
 */
class RememberTokenRepositoryTest extends IntegrationTestCase
{
	private DatabaseRememberTokenRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();

		$this->truncateTable( 'remember_tokens' );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )->willReturn( [ 'adapter' => 'sqlite', 'name' => ':memory:' ] );

		// Use the migrated test database
		$this->repository = new DatabaseRememberTokenRepository( $settings );
		( new \ReflectionProperty( $this->repository, '_pdo' ) )->setValue( $this->repository, $this->pdo );
	}

	public function testCreateFindTouchAndDelete(): void
	{
		$userId = $this->createTestUser();
		$token = $this->repository->create( new RememberToken( $userId, hash( 'sha256', 'browser-a' ), 60 ) );

		$this->assertNotNull( $token->getId() );

		$found = $this->repository->findValidByToken( hash( 'sha256', 'browser-a' ) );

		$this->assertSame( $userId, $found->getUserId() );
		$this->assertNull( $found->getLastUsedAt() );

		$this->repository->touch( $found );
		$this->assertNotNull( $this->repository->findValidByToken( hash( 'sha256', 'browser-a' ) )->getLastUsedAt() );

		$this->assertTrue( $this->repository->deleteByToken( hash( 'sha256', 'browser-a' ) ) );
		$this->assertNull( $this->repository->findValidByToken( hash( 'sha256', 'browser-a' ) ) );
	}

	public function testExpiredTokensAreNotValidAndArePurged(): void
	{
		$userId = $this->createTestUser();

		$expired = new RememberToken( $userId, hash( 'sha256', 'old' ) );
		$expired->setExpiresAt( new DateTimeImmutable( '-1 day' ) );
		$this->repository->create( $expired );
		$this->repository->create( new RememberToken( $userId, hash( 'sha256', 'current' ) ) );

		$this->assertNull( $this->repository->findValidByToken( hash( 'sha256', 'old' ) ) );
		$this->assertSame( 1, $this->repository->deleteExpired() );
		$this->assertNotNull( $this->repository->findValidByToken( hash( 'sha256', 'current' ) ) );
	}

	public function testDeleteByUserIdSignsOutEveryBrowserOfThatUserOnly(): void
	{
		$userId = $this->createTestUser();
		$otherId = $this->createTestUser();

		$this->repository->create( new RememberToken( $userId, hash( 'sha256', 'laptop' ) ) );
		$this->repository->create( new RememberToken( $userId, hash( 'sha256', 'phone' ) ) );
		$this->repository->create( new RememberToken( $otherId, hash( 'sha256', 'other' ) ) );

		$this->assertSame( 2, $this->repository->deleteByUserId( $userId ) );
		$this->assertNotNull( $this->repository->findValidByToken( hash( 'sha256', 'other' ) ) );
	}

	public function testTokenHashIsUnique(): void
	{
		$userId = $this->createTestUser();
		$this->repository->create( new RememberToken( $userId, hash( 'sha256', 'same' ) ) );

		$this->expectException( \PDOException::class );
		$this->repository->create( new RememberToken( $userId, hash( 'sha256', 'same' ) ) );
	}
}
