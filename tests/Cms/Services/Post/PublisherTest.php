<?php

namespace Tests\Cms\Services\Post;

use DateTimeImmutable;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Post\Publisher;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
	private Publisher $_publisher;
	private IPostRepository $_mockPostRepository;

	protected function setUp(): void
	{
		$this->_mockPostRepository = $this->createMock( IPostRepository::class );
		$this->_publisher = new Publisher( $this->_mockPostRepository );
	}

	public function testPublishesDraftPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getStatus() === Post::STATUS_PUBLISHED
					&& $p->getPublishedAt() instanceof DateTimeImmutable;
			} ) )
			->willReturn( true );

		$result = $this->_publisher->publish( $post );

		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertInstanceOf( DateTimeImmutable::class, $result->getPublishedAt() );
	}

	public function testThrowsExceptionWhenPublishingAlreadyPublishedPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_PUBLISHED );

		$this->_mockPostRepository
			->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Post is already published' );

		$this->_publisher->publish( $post );
	}

	public function testUnpublishesPublishedPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setPublishedAt( new DateTimeImmutable() );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getStatus() === Post::STATUS_DRAFT
					&& $p->getPublishedAt() === null;
			} ) )
			->willReturn( true );

		$result = $this->_publisher->unpublish( $post );

		$this->assertEquals( Post::STATUS_DRAFT, $result->getStatus() );
		$this->assertNull( $result->getPublishedAt() );
	}

	public function testThrowsExceptionWhenUnpublishingNonPublishedPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Post is not currently published' );

		$this->_publisher->unpublish( $post );
	}

	public function testSchedulesPostForFuturePublication(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$futureDate = ( new DateTimeImmutable() )->modify( '+1 day' );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) use ( $futureDate ) {
				return $p->getStatus() === Post::STATUS_SCHEDULED
					&& $p->getPublishedAt() instanceof DateTimeImmutable
					&& $p->getPublishedAt()->getTimestamp() === $futureDate->getTimestamp();
			} ) )
			->willReturn( true );

		$result = $this->_publisher->schedule( $post, $futureDate );

		$this->assertEquals( Post::STATUS_SCHEDULED, $result->getStatus() );
		$this->assertEquals( $futureDate->getTimestamp(), $result->getPublishedAt()->getTimestamp() );
	}

	public function testThrowsExceptionWhenSchedulingWithPastDate(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$pastDate = ( new DateTimeImmutable() )->modify( '-1 day' );

		$this->_mockPostRepository
			->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Scheduled publish date must be in the future' );

		$this->_publisher->schedule( $post, $pastDate );
	}

	public function testThrowsExceptionWhenSchedulingWithCurrentTime(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		// Create a date that's exactly now (which is technically in the past when compared)
		$now = new DateTimeImmutable();

		$this->_mockPostRepository
			->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Scheduled publish date must be in the future' );

		$this->_publisher->schedule( $post, $now );
	}

	public function testReturnsUpdatedPostAfterPublishing(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->method( 'update' )
			->willReturn( true );

		$result = $this->_publisher->publish( $post );

		$this->assertSame( $post, $result );
	}

	public function testReturnsUpdatedPostAfterUnpublishing(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setPublishedAt( new DateTimeImmutable() );

		$this->_mockPostRepository
			->method( 'update' )
			->willReturn( true );

		$result = $this->_publisher->unpublish( $post );

		$this->assertSame( $post, $result );
	}

	public function testReturnsUpdatedPostAfterScheduling(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$futureDate = ( new DateTimeImmutable() )->modify( '+1 day' );

		$this->_mockPostRepository
			->method( 'update' )
			->willReturn( true );

		$result = $this->_publisher->schedule( $post, $futureDate );

		$this->assertSame( $post, $result );
	}

	public function testThrowsExceptionWhenPublishPersistenceFails(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( false );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Failed to persist post changes' );

		$this->_publisher->publish( $post );
	}

	public function testThrowsExceptionWhenUnpublishPersistenceFails(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setPublishedAt( new DateTimeImmutable() );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( false );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Failed to persist post changes' );

		$this->_publisher->unpublish( $post );
	}

	public function testThrowsExceptionWhenSchedulePersistenceFails(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setStatus( Post::STATUS_DRAFT );

		$futureDate = ( new DateTimeImmutable() )->modify( '+1 day' );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( false );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Failed to persist post changes' );

		$this->_publisher->schedule( $post, $futureDate );
	}
}
