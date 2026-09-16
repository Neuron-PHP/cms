<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Models\Redirect;
use Neuron\Cms\Repositories\DatabaseRedirectRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseRedirectRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseRedirectRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE redirects (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				from_path VARCHAR(512) NOT NULL UNIQUE,
				to_url VARCHAR(2048) NOT NULL,
				status_code INTEGER NOT NULL DEFAULT 301,
				is_active INTEGER NOT NULL DEFAULT 1,
				preserve_query INTEGER NOT NULL DEFAULT 1,
				notes VARCHAR(500),
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name' => ':memory:'
			] );

		$this->repository = new DatabaseRedirectRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function makeRedirect( array $overrides = [] ): Redirect
	{
		$redirect = new Redirect();
		$redirect->setFromPath( $overrides['from_path'] ?? '/old-page' );
		$redirect->setToUrl( $overrides['to_url'] ?? '/new-page' );
		$redirect->setStatusCode( $overrides['status_code'] ?? Redirect::STATUS_PERMANENT );
		$redirect->setIsActive( $overrides['is_active'] ?? true );
		$redirect->setPreserveQuery( $overrides['preserve_query'] ?? true );
		$redirect->setNotes( $overrides['notes'] ?? 'Moved during redesign' );

		return $this->repository->create( $redirect );
	}

	public function testAllReturnsEmptyArrayWhenNoneExist(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$redirect = $this->makeRedirect();

		$found = $this->repository->findById( $redirect->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( '/old-page', $found->getFromPath() );
		$this->assertSame( '/new-page', $found->getToUrl() );
		$this->assertSame( 301, $found->getStatusCode() );
		$this->assertTrue( $found->isActive() );
		$this->assertTrue( $found->getPreserveQuery() );
		$this->assertSame( 'Moved during redesign', $found->getNotes() );
	}

	public function testFindActiveByFromPath(): void
	{
		$this->makeRedirect();
		$this->makeRedirect( [
			'from_path' => '/inactive',
			'to_url' => '/elsewhere',
			'is_active' => false
		] );

		$found = $this->repository->findActiveByFromPath( '/old-page' );

		$this->assertNotNull( $found );
		$this->assertSame( '/new-page', $found->getToUrl() );
		$this->assertNull( $this->repository->findActiveByFromPath( '/inactive' ) );
		$this->assertNull( $this->repository->findActiveByFromPath( '/missing' ) );
	}

	public function testAllReturnsRedirectsSortedByFromPath(): void
	{
		$this->makeRedirect( [ 'from_path' => '/zeta' ] );
		$this->makeRedirect( [ 'from_path' => '/alpha', 'to_url' => '/a' ] );

		$redirects = $this->repository->all();

		$this->assertCount( 2, $redirects );
		$this->assertSame( '/alpha', $redirects[0]->getFromPath() );
		$this->assertSame( '/zeta', $redirects[1]->getFromPath() );
	}

	public function testUpdateChangesFields(): void
	{
		$redirect = $this->makeRedirect();
		$redirect->setToUrl( 'https://example.com/moved' );
		$redirect->setStatusCode( Redirect::STATUS_TEMPORARY );
		$redirect->setIsActive( false );
		$redirect->setPreserveQuery( false );
		$redirect->setNotes( null );

		$this->repository->update( $redirect );

		$found = $this->repository->findById( $redirect->getId() );
		$this->assertSame( 'https://example.com/moved', $found->getToUrl() );
		$this->assertSame( 302, $found->getStatusCode() );
		$this->assertFalse( $found->isActive() );
		$this->assertFalse( $found->getPreserveQuery() );
		$this->assertNull( $found->getNotes() );
	}

	public function testFromPathExists(): void
	{
		$redirect = $this->makeRedirect();

		$this->assertTrue( $this->repository->fromPathExists( '/old-page' ) );
		$this->assertFalse( $this->repository->fromPathExists( '/old-page', $redirect->getId() ) );
		$this->assertFalse( $this->repository->fromPathExists( '/missing' ) );
	}

	public function testDeleteRemovesRow(): void
	{
		$redirect = $this->makeRedirect();

		$this->assertTrue( $this->repository->delete( $redirect ) );
		$this->assertNull( $this->repository->findById( $redirect->getId() ) );
	}

	public function testFindByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findById( 999 ) );
	}
}
