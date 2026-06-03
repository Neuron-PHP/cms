<?php

namespace Tests\Cms\Services\Revision;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Revision;
use Neuron\Cms\Repositories\IRevisionRepository;
use Neuron\Cms\Services\Revision\RevisionService;
use PHPUnit\Framework\TestCase;

class RevisionServiceTest extends TestCase
{
	public function testRecordPageBuildsRevisionFromModel(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );

		$page = new Page();
		$page->setId( 12 );
		$page->setTitle( 'About Us' );
		$page->setSlug( 'about-us' );
		$page->setContent( '{"blocks":[]}' );
		$page->setAuthorId( 1 );
		$page->setStatus( 'published' );

		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Revision $revision ) {
				$this->assertEquals( Revision::TYPE_PAGE, $revision->getContentType() );
				$this->assertEquals( 12, $revision->getContentId() );
				$this->assertEquals( 'About Us', $revision->getTitle() );
				$this->assertEquals( 'published', $revision->getStatus() );
				$this->assertEquals( Revision::ACTION_UPDATED, $revision->getAction() );

				$snapshot = $revision->getSnapshotData();
				$this->assertEquals( 'about-us', $snapshot['slug'] );
				$this->assertEquals( '{"blocks":[]}', $snapshot['content'] );

				return $revision;
			} );

		$service = new RevisionService( $repository );
		$result = $service->recordPage( $page, Revision::ACTION_UPDATED );

		$this->assertInstanceOf( Revision::class, $result );
	}

	public function testRecordPageReturnsNullWhenNoId(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );
		$repository->expects( $this->never() )->method( 'create' );

		$page = new Page();
		$page->setTitle( 'No ID Yet' );

		$service = new RevisionService( $repository );
		$this->assertNull( $service->recordPage( $page, Revision::ACTION_CREATED ) );
	}

	public function testRecordPostBuildsRevisionFromModel(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );

		$post = new Post();
		$post->setId( 99 );
		$post->setTitle( 'Hello World' );
		$post->setSlug( 'hello-world' );
		$post->setContent( '{"blocks":[]}' );
		$post->setAuthorId( 1 );
		$post->setStatus( 'draft' );

		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Revision $revision ) {
				$this->assertEquals( Revision::TYPE_POST, $revision->getContentType() );
				$this->assertEquals( 99, $revision->getContentId() );
				$this->assertEquals( Revision::ACTION_CREATED, $revision->getAction() );

				$snapshot = $revision->getSnapshotData();
				$this->assertEquals( '{"blocks":[]}', $snapshot['content_raw'] );

				return $revision;
			} );

		$service = new RevisionService( $repository );
		$result = $service->recordPost( $post, Revision::ACTION_CREATED );

		$this->assertInstanceOf( Revision::class, $result );
	}

	public function testListForPageDelegatesToRepository(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );
		$expected = [ new Revision(), new Revision() ];

		$repository
			->expects( $this->once() )
			->method( 'getForContent' )
			->with( Revision::TYPE_PAGE, 5 )
			->willReturn( $expected );

		$service = new RevisionService( $repository );
		$this->assertSame( $expected, $service->listForPage( 5 ) );
	}

	public function testListForPostDelegatesToRepository(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );

		$repository
			->expects( $this->once() )
			->method( 'getForContent' )
			->with( Revision::TYPE_POST, 8 )
			->willReturn( [] );

		$service = new RevisionService( $repository );
		$this->assertSame( [], $service->listForPost( 8 ) );
	}

	public function testFindDelegatesToRepository(): void
	{
		$repository = $this->createMock( IRevisionRepository::class );
		$revision = new Revision();

		$repository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 3 )
			->willReturn( $revision );

		$service = new RevisionService( $repository );
		$this->assertSame( $revision, $service->find( 3 ) );
	}
}
