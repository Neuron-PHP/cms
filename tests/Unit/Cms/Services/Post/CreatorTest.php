<?php

namespace Tests\Cms\Services\Post;

use DateTimeImmutable;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Post\Creator;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
	private Creator $_creator;
	private IPostRepository $_mockPostRepository;
	private ICategoryRepository $_mockCategoryRepository;
	private TagResolver $_mockTagResolver;

	protected function setUp(): void
	{
		$this->_mockPostRepository = $this->createMock( IPostRepository::class );
		$this->_mockCategoryRepository = $this->createMock( ICategoryRepository::class );
		$this->_mockTagResolver = $this->createMock( TagResolver::class );

		$this->_creator = new Creator(
			$this->_mockPostRepository,
			$this->_mockCategoryRepository,
			$this->_mockTagResolver
		);
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		string $title,
		string $content,
		int $authorId,
		string $status,
		?string $slug = null,
		?string $excerpt = null,
		?string $featuredImage = null,
		?string $publishedAt = null
	): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/posts/create-post-request.yaml" );
		$dto = $factory->create();

		$dto->title = $title;
		$dto->content = $content;
		$dto->author_id = $authorId;
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

	public function testCreatesPostWithRequiredFields(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$editorJsContent = '{"blocks":[{"type":"paragraph","data":{"text":"Test body content"}}]}';

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) use ( $editorJsContent ) {
				return $post->getTitle() === 'Test Post'
					&& $post->getContentRaw() === $editorJsContent
					&& $post->getBody() === 'Test body content'
					&& $post->getAuthorId() === 1
					&& $post->getStatus() === Post::STATUS_DRAFT
					&& $post->getCreatedAt() instanceof DateTimeImmutable;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post',
			$editorJsContent,
			1,
			Post::STATUS_DRAFT
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'Test Post', $result->getTitle() );
		$this->assertEquals( $editorJsContent, $result->getContentRaw() );
		$this->assertEquals( 'Test body content', $result->getBody() );
	}

	public function testGeneratesSlugWhenNotProvided(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getSlug() === 'test-post-title';
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'test-post-title', $result->getSlug() );
	}

	public function testUsesCustomSlugWhenProvided(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getSlug() === 'custom-slug';
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT,
			'custom-slug'
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'custom-slug', $result->getSlug() );
	}

	public function testSetsPublishedDateForPublishedPosts(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getStatus() === Post::STATUS_PUBLISHED
					&& $post->getPublishedAt() instanceof DateTimeImmutable;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Published Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_PUBLISHED
		);

		$result = $this->_creator->create( $dto );

		$this->assertInstanceOf( DateTimeImmutable::class, $result->getPublishedAt() );
	}

	public function testDoesNotSetPublishedDateForDraftPosts(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getStatus() === Post::STATUS_DRAFT
					&& $post->getPublishedAt() === null;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Draft Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT
		);

		$result = $this->_creator->create( $dto );

		$this->assertNull( $result->getPublishedAt() );
	}

	public function testAttachesCategoriesToPost(): void
	{
		$category1 = new Category();
		$category1->setId( 1 );
		$category1->setName( 'Technology' );

		$category2 = new Category();
		$category2->setId( 2 );
		$category2->setName( 'Programming' );

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
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				$categories = $post->getCategories();
				return count( $categories ) === 2
					&& $categories[0]->getName() === 'Technology'
					&& $categories[1]->getName() === 'Programming';
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT
		);

		$result = $this->_creator->create( $dto, [ 1, 2 ] );

		$this->assertCount( 2, $result->getCategories() );
	}

	public function testResolvesTags(): void
	{
		$tag1 = new Tag();
		$tag1->setId( 1 );
		$tag1->setName( 'PHP' );

		$tag2 = new Tag();
		$tag2->setId( 2 );
		$tag2->setName( 'Testing' );

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
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				$tags = $post->getTags();
				return count( $tags ) === 2
					&& $tags[0]->getName() === 'PHP'
					&& $tags[1]->getName() === 'Testing';
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT
		);

		$result = $this->_creator->create( $dto, [], 'PHP, Testing' );

		$this->assertCount( 2, $result->getTags() );
	}

	public function testSetsOptionalFields(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getExcerpt() === 'Test excerpt'
					&& $post->getFeaturedImage() === 'image.jpg';
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			Post::STATUS_DRAFT,
			null,
			'Test excerpt',
			'image.jpg'
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'Test excerpt', $result->getExcerpt() );
		$this->assertEquals( 'image.jpg', $result->getFeaturedImage() );
	}

	public function testScheduledPostRequiresPublishedDate(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Scheduled posts require a published date' );

		$dto = $this->createDto(
			'Scheduled Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			'scheduled',  // Scheduled status
			null,  // No slug
			null,  // No excerpt
			null,  // No featured image
			null   // No published date - THIS SHOULD THROW EXCEPTION
		);

		$this->_creator->create( $dto );
	}

	public function testScheduledPostWithPublishedDateSucceeds(): void
	{
		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$publishedDate = '2025-12-31T23:59';

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Post $post ) {
				return $post->getStatus() === 'scheduled'
					&& $post->getPublishedAt() instanceof DateTimeImmutable;
			} ) )
			->willReturnArgument( 0 );

		$dto = $this->createDto(
			'Scheduled Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			'scheduled',
			null,
			null,
			null,
			$publishedDate
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( 'scheduled', $result->getStatus() );
		$this->assertInstanceOf( DateTimeImmutable::class, $result->getPublishedAt() );
	}

	public function testInvalidPublishedDateThrowsException(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			'published',
			null,
			null,
			null,
			'2024-13-01T10:00'  // Invalid month
		);

		$this->_creator->create( $dto );
	}

	public function testInvalidDayThrowsException(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			'published',
			null,
			null,
			null,
			'2024-01-32T10:00'  // Invalid day
		);

		$this->_creator->create( $dto );
	}

	public function testInvalidHourThrowsException(): void
	{
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid published date format' );

		$dto = $this->createDto(
			'Test Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			1,
			'published',
			null,
			null,
			null,
			'2024-01-01T25:00'  // Invalid hour
		);

		$this->_creator->create( $dto );
	}
}
