<?php

namespace Tests\Cms\Services\Post;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Post\Updater;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
	private Updater $_updater;
	private IPostRepository $_mockPostRepository;
	private ICategoryRepository $_mockCategoryRepository;
	private TagResolver $_mockTagResolver;

	protected function setUp(): void
	{
		$this->_mockPostRepository = $this->createMock( IPostRepository::class );
		$this->_mockCategoryRepository = $this->createMock( ICategoryRepository::class );
		$this->_mockTagResolver = $this->createMock( TagResolver::class );

		$this->_updater = new Updater(
			$this->_mockPostRepository,
			$this->_mockCategoryRepository,
			$this->_mockTagResolver
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		int $id,
		string $title,
		string $content,
		string $status,
		?string $slug = null,
		?string $excerpt = null,
		?string $featuredImage = null,
		?string $publishedAt = null
	): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/posts/update-post-request.yaml" );
		$dto = $factory->create();

		$dto->id = $id;
		$dto->title = $title;
		$dto->content = $content;
		$dto->status = $status;

		if( $slug !== null )
		{
			$dto->slug = $slug;
		}
		if( $excerpt !== null )
		{
			$dto->excerpt = $excerpt;
		}
		if( $featuredImage !== null )
		{
			$dto->featured_image = $featuredImage;
		}
		if( $publishedAt !== null )
		{
			$dto->published_at = $publishedAt;
		}

		return $dto;
	}

	public function testUpdatesPostWithRequiredFields(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setContent( '{"blocks":[{"type":"paragraph","data":{"text":"Original Body"}}]}' );

		$updatedContent = '{"blocks":[{"type":"paragraph","data":{"text":"Updated Body"}}]}';

		// Mock findById to return the post
		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) use ( $updatedContent ) {
				return $p->getTitle() === 'Updated Title'
					&& $p->getContentRaw() === $updatedContent
					&& $p->getBody() === 'Updated Body'
					&& $p->getStatus() === Post::STATUS_PUBLISHED;
			} ) );

		$dto = $this->createDto(
			1,
			'Updated Title',
			$updatedContent,
			Post::STATUS_PUBLISHED
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'Updated Title', $result->getTitle() );
		$this->assertEquals( $updatedContent, $result->getContentRaw() );
		$this->assertEquals( 'Updated Body', $result->getBody() );
		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
	}

	public function testGeneratesSlugWhenNotProvided(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getSlug() === 'new-post-title';
			} ) );

		$dto = $this->createDto(
			1,
			'New Post Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'new-post-title', $result->getSlug() );
	}

	public function testUsesProvidedSlug(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getSlug() === 'custom-slug';
			} ) );

		$dto = $this->createDto(
			1,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			'custom-slug'
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'custom-slug', $result->getSlug() );
	}

	public function testUpdatesCategories(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$category1 = new Category();
		$category1->setId( 1 );
		$category1->setName( 'Tech' );

		$category2 = new Category();
		$category2->setId( 2 );
		$category2->setName( 'News' );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'findByIds' )
			->with( [ 1, 2 ] )
			->willReturn( [ $category1, $category2 ] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				$categories = $p->getCategories();
				return count( $categories ) === 2
					&& $categories[0]->getName() === 'Tech'
					&& $categories[1]->getName() === 'News';
			} ) );

		$dto = $this->createDto(
			1,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$result = $this->_updater->update( $dto, [ 1, 2 ] );

		$this->assertCount( 2, $result->getCategories() );
	}

	public function testUpdatesTags(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$tag1 = new Tag();
		$tag1->setId( 1 );
		$tag1->setName( 'PHP' );

		$tag2 = new Tag();
		$tag2->setId( 2 );
		$tag2->setName( 'Testing' );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->expects( $this->once() )
			->method( 'resolveFromString' )
			->with( 'PHP, Testing' )
			->willReturn( [ $tag1, $tag2 ] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				$tags = $p->getTags();
				return count( $tags ) === 2
					&& $tags[0]->getName() === 'PHP'
					&& $tags[1]->getName() === 'Testing';
			} ) );

		$dto = $this->createDto(
			1,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$result = $this->_updater->update( $dto, [], 'PHP, Testing' );

		$this->assertCount( 2, $result->getTags() );
	}

	public function testUpdatesOptionalFields(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getExcerpt() === 'New excerpt'
					&& $p->getFeaturedImage() === 'new-image.jpg';
			} ) );

		$dto = $this->createDto(
			1,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			null,
			'New excerpt',
			'new-image.jpg'
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'New excerpt', $result->getExcerpt() );
		$this->assertEquals( 'new-image.jpg', $result->getFeaturedImage() );
	}

	public function testReturnsUpdatedPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original' );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->method( 'update' );

		$dto = $this->createDto(
			1,
			'Updated',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$result = $this->_updater->update( $dto );

		$this->assertSame( $post, $result );
		$this->assertEquals( 'Updated', $result->getTitle() );
	}

	public function testSetsPublishedAtWhenChangingToPublished(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Draft Post' );
		$post->setStatus( Post::STATUS_DRAFT );
		// Ensure publishedAt is not set
		$this->assertNull( $post->getPublishedAt() );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) {
				return $p->getStatus() === Post::STATUS_PUBLISHED
					&& $p->getPublishedAt() instanceof \DateTimeImmutable;
			} ) );

		$dto = $this->createDto(
			1,
			'Published Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_PUBLISHED
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $result->getPublishedAt() );
	}

	public function testDoesNotOverwriteExistingPublishedAt(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Published Post' );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$existingPublishedAt = new \DateTimeImmutable( '2024-01-01 12:00:00' );
		$post->setPublishedAt( $existingPublishedAt );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $p ) use ( $existingPublishedAt ) {
				return $p->getStatus() === Post::STATUS_PUBLISHED
					&& $p->getPublishedAt() === $existingPublishedAt;
			} ) );

		$dto = $this->createDto(
			1,
			'Updated Published Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_PUBLISHED
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertSame( $existingPublishedAt, $result->getPublishedAt() );
	}

	public function testThrowsExceptionWhenPostNotFound(): void
	{
		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Post with ID 999 not found' );

		$dto = $this->createDto(
			999,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$this->_updater->update( $dto );
	}

	public function testScheduledPostRequiresPublishedDate(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Scheduled posts require a published date' );

		$dto = $this->createDto(
			1,
			'Updated Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			'scheduled',  // Scheduled status
			null,  // No slug
			null,  // No excerpt
			null,  // No featured image
			null   // No published date - THIS SHOULD THROW EXCEPTION
		);

		$this->_updater->update( $dto );
	}

	public function testScheduledPostWithPublishedDateSucceeds(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$publishedDate = '2025-12-31T23:59';

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Post $post ) {
				return $post->getStatus() === 'scheduled'
					&& $post->getPublishedAt() instanceof \DateTimeImmutable;
			} ) );

		$dto = $this->createDto(
			1,
			'Updated Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			'scheduled',
			null,
			null,
			null,
			$publishedDate
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'scheduled', $result->getStatus() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $result->getPublishedAt() );
	}

	public function testInvalidPublishedDateThrowsException(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			1,
			'Updated Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			'published',
			null,
			null,
			null,
			'2024-13-01T10:00'  // Invalid month
		);

		$this->_updater->update( $dto );
	}

	public function testInvalidDayThrowsException(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			1,
			'Updated Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			'published',
			null,
			null,
			null,
			'2024-01-32T10:00'  // Invalid day
		);

		$this->_updater->update( $dto );
	}

	public function testInvalidHourThrowsException(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setStatus( Post::STATUS_DRAFT );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $post );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			1,
			'Updated Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			'published',
			null,
			null,
			null,
			'2024-01-01T25:00'  // Invalid hour
		);

		$this->_updater->update( $dto );
	}
}
