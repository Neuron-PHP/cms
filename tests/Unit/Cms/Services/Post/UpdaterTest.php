<?php

namespace Tests\Cms\Services\Post;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Post\Updater;
use Neuron\Cms\Services\Tag\Resolver as TagResolver;
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

	public function testUpdatesPostWithRequiredFields(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original Title' );
		$post->setContent( '{"blocks":[{"type":"paragraph","data":{"text":"Original Body"}}]}' );

		$updatedContent = '{"blocks":[{"type":"paragraph","data":{"text":"Updated Body"}}]}';

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

		$result = $this->_updater->update(
			$post,
			'Updated Title',
			$updatedContent,
			Post::STATUS_PUBLISHED
		);

		$this->assertEquals( 'Updated Title', $result->getTitle() );
		$this->assertEquals( $updatedContent, $result->getContentRaw() );
		$this->assertEquals( 'Updated Body', $result->getBody() );
		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
	}

	public function testGeneratesSlugWhenNotProvided(): void
	{
		$post = new Post();
		$post->setId( 1 );

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

		$result = $this->_updater->update(
			$post,
			'New Post Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

		$this->assertEquals( 'new-post-title', $result->getSlug() );
	}

	public function testUsesProvidedSlug(): void
	{
		$post = new Post();
		$post->setId( 1 );

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

		$result = $this->_updater->update(
			$post,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			'custom-slug'
		);

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

		$result = $this->_updater->update(
			$post,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			null,
			null,
			null,
			[ 1, 2 ]
		);

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

		$result = $this->_updater->update(
			$post,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			null,
			null,
			null,
			[],
			'PHP, Testing'
		);

		$this->assertCount( 2, $result->getTags() );
	}

	public function testUpdatesOptionalFields(): void
	{
		$post = new Post();
		$post->setId( 1 );

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

		$result = $this->_updater->update(
			$post,
			'Title',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT,
			null,
			'New excerpt',
			'new-image.jpg'
		);

		$this->assertEquals( 'New excerpt', $result->getExcerpt() );
		$this->assertEquals( 'new-image.jpg', $result->getFeaturedImage() );
	}

	public function testReturnsUpdatedPost(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Original' );

		$this->_mockCategoryRepository
			->method( 'findByIds' )
			->willReturn( [] );

		$this->_mockTagResolver
			->method( 'resolveFromString' )
			->willReturn( [] );

		$this->_mockPostRepository
			->method( 'update' );

		$result = $this->_updater->update(
			$post,
			'Updated',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_DRAFT
		);

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

		$result = $this->_updater->update(
			$post,
			'Published Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_PUBLISHED
		);

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

		$result = $this->_updater->update(
			$post,
			'Updated Published Post',
			'{"blocks":[{"type":"paragraph","data":{"text":"Body"}}]}',
			Post::STATUS_PUBLISHED
		);

		$this->assertEquals( Post::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertSame( $existingPublishedAt, $result->getPublishedAt() );
	}
}
