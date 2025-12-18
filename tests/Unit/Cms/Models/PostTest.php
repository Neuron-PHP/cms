<?php

namespace Tests\Cms\Models;

use DateTimeImmutable;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use Neuron\Cms\Models\Tag;
use Neuron\Cms\Models\User;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
	public function testCanCreatePost(): void
	{
		$post = new Post();
		$this->assertInstanceOf( Post::class, $post );
		$this->assertNull( $post->getId() );
		$this->assertInstanceOf( DateTimeImmutable::class, $post->getCreatedAt() );
	}

	public function testCanSetAndGetId(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$this->assertEquals( 1, $post->getId() );
	}

	public function testCanSetAndGetTitle(): void
	{
		$post = new Post();
		$post->setTitle( 'Test Post' );
		$this->assertEquals( 'Test Post', $post->getTitle() );
	}

	public function testCanSetAndGetSlug(): void
	{
		$post = new Post();
		$post->setSlug( 'test-post' );
		$this->assertEquals( 'test-post', $post->getSlug() );
	}

	public function testCanSetAndGetBody(): void
	{
		$post = new Post();
		$post->setBody( 'This is the body content.' );
		$this->assertEquals( 'This is the body content.', $post->getBody() );
	}

	public function testCanSetAndGetExcerpt(): void
	{
		$post = new Post();
		$post->setExcerpt( 'This is an excerpt' );
		$this->assertEquals( 'This is an excerpt', $post->getExcerpt() );
	}

	public function testCanSetAndGetFeaturedImage(): void
	{
		$post = new Post();
		$post->setFeaturedImage( '/images/featured.jpg' );
		$this->assertEquals( '/images/featured.jpg', $post->getFeaturedImage() );
	}

	public function testCanSetAndGetAuthorId(): void
	{
		$post = new Post();
		$post->setAuthorId( 5 );
		$this->assertEquals( 5, $post->getAuthorId() );
	}

	public function testCanSetAndGetStatus(): void
	{
		$post = new Post();
		$post->setStatus( Post::STATUS_PUBLISHED );
		$this->assertEquals( Post::STATUS_PUBLISHED, $post->getStatus() );
	}

	public function testCanSetAndGetViewCount(): void
	{
		$post = new Post();
		$post->setViewCount( 100 );
		$this->assertEquals( 100, $post->getViewCount() );
	}

	public function testCanIncrementViewCount(): void
	{
		$post = new Post();
		$post->setViewCount( 5 );
		$post->incrementViewCount();
		$this->assertEquals( 6, $post->getViewCount() );
	}

	public function testCanSetAndGetPublishedAt(): void
	{
		$post = new Post();
		$date = new DateTimeImmutable( '2025-01-01 12:00:00' );
		$post->setPublishedAt( $date );
		$this->assertEquals( $date, $post->getPublishedAt() );
	}

	public function testStatusHelpers(): void
	{
		$post = new Post();

		// Test draft
		$post->setStatus( Post::STATUS_DRAFT );
		$this->assertTrue( $post->isDraft() );
		$this->assertFalse( $post->isPublished() );
		$this->assertFalse( $post->isScheduled() );

		// Test published
		$post->setStatus( Post::STATUS_PUBLISHED );
		$this->assertFalse( $post->isDraft() );
		$this->assertTrue( $post->isPublished() );
		$this->assertFalse( $post->isScheduled() );

		// Test scheduled
		$post->setStatus( Post::STATUS_SCHEDULED );
		$this->assertFalse( $post->isDraft() );
		$this->assertFalse( $post->isPublished() );
		$this->assertTrue( $post->isScheduled() );
	}

	public function testCanAddAndGetCategories(): void
	{
		$post = new Post();
		$category1 = new Category();
		$category1->setId( 1 );
		$category1->setName( 'Tech' );

		$category2 = new Category();
		$category2->setId( 2 );
		$category2->setName( 'News' );

		$post->addCategory( $category1 );
		$post->addCategory( $category2 );

		$categories = $post->getCategories();
		$this->assertCount( 2, $categories );
		$this->assertEquals( 'Tech', $categories[0]->getName() );
		$this->assertEquals( 'News', $categories[1]->getName() );
	}

	public function testCanRemoveCategory(): void
	{
		$post = new Post();
		$category1 = new Category();
		$category1->setId( 1 );
		$category1->setName( 'Tech' );

		$category2 = new Category();
		$category2->setId( 2 );
		$category2->setName( 'News' );

		$post->addCategory( $category1 );
		$post->addCategory( $category2 );
		$this->assertCount( 2, $post->getCategories() );

		$post->removeCategory( $category1 );
		$this->assertCount( 1, $post->getCategories() );
	}

	public function testCanCheckIfHasCategory(): void
	{
		$post = new Post();
		$category1 = new Category();
		$category1->setId( 1 );

		$category2 = new Category();
		$category2->setId( 2 );

		$post->addCategory( $category1 );

		$this->assertTrue( $post->hasCategory( $category1 ) );
		$this->assertFalse( $post->hasCategory( $category2 ) );
	}

	public function testCanAddAndGetTags(): void
	{
		$post = new Post();
		$tag1 = new Tag();
		$tag1->setId( 1 );
		$tag1->setName( 'php' );

		$tag2 = new Tag();
		$tag2->setId( 2 );
		$tag2->setName( 'coding' );

		$post->addTag( $tag1 );
		$post->addTag( $tag2 );

		$tags = $post->getTags();
		$this->assertCount( 2, $tags );
		$this->assertEquals( 'php', $tags[0]->getName() );
		$this->assertEquals( 'coding', $tags[1]->getName() );
	}

	public function testCanRemoveTag(): void
	{
		$post = new Post();
		$tag1 = new Tag();
		$tag1->setId( 1 );

		$tag2 = new Tag();
		$tag2->setId( 2 );

		$post->addTag( $tag1 );
		$post->addTag( $tag2 );
		$this->assertCount( 2, $post->getTags() );

		$post->removeTag( $tag1 );
		$this->assertCount( 1, $post->getTags() );
	}

	public function testCanCheckIfHasTag(): void
	{
		$post = new Post();
		$tag1 = new Tag();
		$tag1->setId( 1 );

		$tag2 = new Tag();
		$tag2->setId( 2 );

		$post->addTag( $tag1 );

		$this->assertTrue( $post->hasTag( $tag1 ) );
		$this->assertFalse( $post->hasTag( $tag2 ) );
	}

	public function testFromArray(): void
	{
		$data = [
			'id' => 1,
			'title' => 'Test Post',
			'slug' => 'test-post',
			'body' => 'Body content',
			'excerpt' => 'Excerpt',
			'featured_image' => '/image.jpg',
			'author_id' => 5,
			'status' => Post::STATUS_PUBLISHED,
			'view_count' => 100,
			'published_at' => '2025-01-01 12:00:00',
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-01 11:00:00'
		];

		$post = Post::fromArray( $data );

		$this->assertEquals( 1, $post->getId() );
		$this->assertEquals( 'Test Post', $post->getTitle() );
		$this->assertEquals( 'test-post', $post->getSlug() );
		$this->assertEquals( 'Body content', $post->getBody() );
		$this->assertEquals( 'Excerpt', $post->getExcerpt() );
		$this->assertEquals( '/image.jpg', $post->getFeaturedImage() );
		$this->assertEquals( 5, $post->getAuthorId() );
		$this->assertEquals( Post::STATUS_PUBLISHED, $post->getStatus() );
		$this->assertEquals( 100, $post->getViewCount() );
	}

	public function testToArray(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setBody( 'Body content' );
		$post->setExcerpt( 'Excerpt' );
		$post->setFeaturedImage( '/image.jpg' );
		$post->setAuthorId( 5 );
		$post->setStatus( Post::STATUS_PUBLISHED );
		$post->setViewCount( 100 );

		$array = $post->toArray();

		$this->assertEquals( 1, $array['id'] );
		$this->assertEquals( 'Test Post', $array['title'] );
		$this->assertEquals( 'test-post', $array['slug'] );
		$this->assertEquals( 'Body content', $array['body'] );
		$this->assertEquals( 'Excerpt', $array['excerpt'] );
		$this->assertEquals( '/image.jpg', $array['featured_image'] );
		$this->assertEquals( 5, $array['author_id'] );
		$this->assertEquals( Post::STATUS_PUBLISHED, $array['status'] );
		$this->assertEquals( 100, $array['view_count'] );
	}

	public function testSetAuthorAlsoSetsAuthorId(): void
	{
		$post = new Post();
		$user = new User();
		$user->setId( 10 );
		$user->setUsername( 'testuser' );

		$post->setAuthor( $user );

		$this->assertEquals( $user, $post->getAuthor() );
		$this->assertEquals( 10, $post->getAuthorId() );
	}

	public function testAddingDuplicateCategoryDoesNotCreateDuplicate(): void
	{
		$post = new Post();
		$category = new Category();
		$category->setId( 1 );

		$post->addCategory( $category );
		$post->addCategory( $category );

		$this->assertCount( 1, $post->getCategories() );
	}

	public function testAddingDuplicateTagDoesNotCreateDuplicate(): void
	{
		$post = new Post();
		$tag = new Tag();
		$tag->setId( 1 );

		$post->addTag( $tag );
		$post->addTag( $tag );

		$this->assertCount( 1, $post->getTags() );
	}
}
