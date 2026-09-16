<?php

namespace Tests\Unit\Cms\Services\Redirect;

use Neuron\Cms\Models\Redirect;
use Neuron\Cms\Repositories\IRedirectRepository;
use Neuron\Cms\Services\Redirect\RedirectService;
use PHPUnit\Framework\TestCase;

class RedirectServiceTest extends TestCase
{
	private $repository;
	private RedirectService $service;

	protected function setUp(): void
	{
		$this->repository = $this->createMock( IRedirectRepository::class );
		$this->service = new RedirectService( $this->repository );
	}

	public function testNormalizePathAddsLeadingSlashAndStripsTrailing(): void
	{
		$this->assertSame( '/old-page', RedirectService::normalizePath( 'old-page/' ) );
		$this->assertSame( '/', RedirectService::normalizePath( '' ) );
		$this->assertSame( '/about', RedirectService::normalizePath( 'https://example.com/about?x=1#top' ) );
		$this->assertSame( '/a/b', RedirectService::normalizePath( '//a//b/' ) );
	}

	public function testNormalizeDestinationKeepsAbsoluteUrls(): void
	{
		$this->assertSame(
			'https://example.com/moved?ref=1',
			RedirectService::normalizeDestination( 'https://example.com/moved?ref=1' )
		);
		$this->assertSame( '/new-page', RedirectService::normalizeDestination( 'new-page/' ) );
	}

	public function testIsProtectedPath(): void
	{
		$this->assertTrue( RedirectService::isProtectedPath( '/admin' ) );
		$this->assertTrue( RedirectService::isProtectedPath( '/admin/pages' ) );
		$this->assertFalse( RedirectService::isProtectedPath( '/administration' ) );
		$this->assertFalse( RedirectService::isProtectedPath( '/about' ) );
	}

	public function testResolveReturnsActiveMatch(): void
	{
		$redirect = $this->redirect( '/old-page', '/new-page' );
		$this->repository->method( 'findActiveByFromPath' )->with( '/old-page' )->willReturn( $redirect );

		$found = $this->service->resolve( 'old-page/' );

		$this->assertSame( $redirect, $found );
	}

	public function testResolveSkipsProtectedPaths(): void
	{
		$this->repository->expects( $this->never() )->method( 'findActiveByFromPath' );

		$this->assertNull( $this->service->resolve( '/admin/users' ) );
	}

	public function testResolveReturnsNullWhenLookupFails(): void
	{
		$this->repository->method( 'findActiveByFromPath' )
			->willThrowException( new \RuntimeException( 'no such table' ) );

		$this->assertNull( $this->service->resolve( '/old-page' ) );
	}

	public function testDestinationUrlPreservesQueryWhenEnabled(): void
	{
		$redirect = $this->redirect( '/old', '/new' );

		$this->assertSame( '/new?utm=1', $this->service->destinationUrl( $redirect, 'utm=1' ) );
	}

	public function testDestinationUrlDoesNotAppendWhenDestinationHasQuery(): void
	{
		$redirect = $this->redirect( '/old', '/new?already=1' );

		$this->assertSame( '/new?already=1', $this->service->destinationUrl( $redirect, 'utm=1' ) );
	}

	public function testDestinationUrlSkipsQueryWhenDisabled(): void
	{
		$redirect = $this->redirect( '/old', '/new' );
		$redirect->setPreserveQuery( false );

		$this->assertSame( '/new', $this->service->destinationUrl( $redirect, 'utm=1' ) );
	}

	public function testValidateRejectsEmptySource(): void
	{
		$this->assertSame( 'Source path is required.', $this->service->validate( '', '/new' ) );
	}

	public function testValidateRejectsProtectedSource(): void
	{
		$this->assertSame( 'Admin paths cannot be redirected.', $this->service->validate( '/admin/users', '/elsewhere' ) );
	}

	public function testValidateRejectsSameSourceAndDestination(): void
	{
		$this->assertSame(
			'Source and destination cannot be the same.',
			$this->service->validate( '/about/', '/about' )
		);
	}

	public function testValidateRejectsInvalidStatus(): void
	{
		$this->assertSame( 'Status must be 301 or 302.', $this->service->validate( '/old', '/new', 307 ) );
	}

	public function testValidateRejectsDuplicateFromPath(): void
	{
		$this->repository->method( 'fromPathExists' )->with( '/old', null )->willReturn( true );

		$this->assertSame( 'A redirect from that path already exists.', $this->service->validate( '/old', '/new' ) );
	}

	public function testValidateRejectsReverseLoop(): void
	{
		$reverse = $this->redirect( '/new', '/old' );
		$reverse->setId( 9 );
		$this->repository->method( 'fromPathExists' )->willReturn( false );
		$this->repository->method( 'findActiveByFromPath' )->with( '/new' )->willReturn( $reverse );

		$this->assertSame(
			'That destination already redirects back to this path.',
			$this->service->validate( '/old', '/new' )
		);
	}

	public function testValidateAcceptsValidRule(): void
	{
		$this->repository->method( 'fromPathExists' )->willReturn( false );
		$this->repository->method( 'findActiveByFromPath' )->willReturn( null );

		$this->assertNull( $this->service->validate( 'old-page/', '/new-page' ) );
	}

	public function testNormalizeForSave(): void
	{
		$redirect = $this->redirect( 'old-page/', 'new-page/' );

		$this->service->normalizeForSave( $redirect );

		$this->assertSame( '/old-page', $redirect->getFromPath() );
		$this->assertSame( '/new-page', $redirect->getToUrl() );
	}

	private function redirect( string $from, string $to ): Redirect
	{
		$redirect = new Redirect();
		$redirect->setFromPath( $from );
		$redirect->setToUrl( $to );

		return $redirect;
	}
}
