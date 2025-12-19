<?php

namespace Tests\Cms\Services\Widget;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Models\Post;

class WidgetRendererTest extends TestCase
{
	public function testRenderUnknownWidgetReturnsComment(): void
	{
		$renderer = new WidgetRenderer();
		$result = $renderer->render( 'unknown-widget', [] );

		$this->assertStringContainsString( '<!-- Unknown widget: unknown-widget -->', $result );
	}

	public function testRenderLatestPostsWithoutRepositoryReturnsComment(): void
	{
		$renderer = new WidgetRenderer(); // No repository injected
		$result = $renderer->render( 'latest-posts', [] );

		$this->assertStringContainsString( '<!-- Latest posts widget requires PostRepository -->', $result );
	}

	public function testRenderLatestPostsWithNoPosts(): void
	{
		$repository = $this->createMock( IPostRepository::class );
		$repository
			->expects( $this->once() )
			->method( 'getPublished' )
			->with( 5 ) // Default limit
			->willReturn( [] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		$this->assertStringContainsString( "No posts available", $result );
		$this->assertStringContainsString( "latest-posts-widget", $result );
	}

	public function testRenderLatestPostsWithPosts(): void
	{
		$post1 = new Post();
		$post1->setTitle( 'Test Post 1' );
		$post1->setSlug( 'test-post-1' );
		$post1->setExcerpt( 'This is excerpt 1' );
		$post1->setPublishedAt( new \DateTimeImmutable( '2024-01-01' ) );

		$post2 = new Post();
		$post2->setTitle( 'Test Post 2' );
		$post2->setSlug( 'test-post-2' );
		$post2->setExcerpt( 'This is excerpt 2' );
		$post2->setPublishedAt( new \DateTimeImmutable( '2024-01-02' ) );

		$repository = $this->createMock( IPostRepository::class );
		$repository
			->expects( $this->once() )
			->method( 'getPublished' )
			->willReturn( [$post1, $post2] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		$this->assertStringContainsString( 'latest-posts-widget', $result );
		$this->assertStringContainsString( 'Latest Posts', $result );
		$this->assertStringContainsString( 'Test Post 1', $result );
		$this->assertStringContainsString( 'Test Post 2', $result );
		$this->assertStringContainsString( 'test-post-1', $result );
		$this->assertStringContainsString( 'test-post-2', $result );
		$this->assertStringContainsString( 'This is excerpt 1', $result );
		$this->assertStringContainsString( 'This is excerpt 2', $result );
		$this->assertStringContainsString( 'January 1, 2024', $result );
		$this->assertStringContainsString( 'January 2, 2024', $result );
	}

	public function testRenderLatestPostsWithCustomLimit(): void
	{
		$repository = $this->createMock( IPostRepository::class );
		$repository
			->expects( $this->once() )
			->method( 'getPublished' )
			->with( 10 ) // Custom limit
			->willReturn( [] );

		$renderer = new WidgetRenderer( $repository );
		$renderer->render( 'latest-posts', ['limit' => 10] );
	}

	public function testRenderLatestPostsWithPostWithoutExcerpt(): void
	{
		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		// No excerpt set
		$post->setPublishedAt( new \DateTimeImmutable( '2024-01-01' ) );

		$repository = $this->createMock( IPostRepository::class );
		$repository
			->method( 'getPublished' )
			->willReturn( [$post] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		$this->assertStringContainsString( 'Test Post', $result );
		// Should not have excerpt section
		$this->assertStringNotContainsString( '<p class=\'mb-0\'>', $result );
	}

	public function testRenderLatestPostsWithPostWithoutPublishedDate(): void
	{
		$post = new Post();
		$post->setTitle( 'Test Post' );
		$post->setSlug( 'test-post' );
		$post->setExcerpt( 'Test excerpt' );
		// No published date set

		$repository = $this->createMock( IPostRepository::class );
		$repository
			->method( 'getPublished' )
			->willReturn( [$post] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		$this->assertStringContainsString( 'Test Post', $result );
		// Should not have date paragraph
		$this->assertStringNotContainsString( '<p class=\'text-muted small mb-2\'>', $result );
	}

	public function testRenderLatestPostsEscapesHtml(): void
	{
		$post = new Post();
		$post->setTitle( '<script>alert("XSS")</script>' );
		$post->setSlug( 'safe-slug' );
		$post->setExcerpt( '<script>alert("XSS2")</script>' );
		$post->setPublishedAt( new \DateTimeImmutable( '2024-01-01' ) );

		$repository = $this->createMock( IPostRepository::class );
		$repository
			->method( 'getPublished' )
			->willReturn( [$post] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		// HTML should be escaped
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	public function testRenderLatestPostsGeneratesCorrectHtmlStructure(): void
	{
		$post = new Post();
		$post->setTitle( 'Test' );
		$post->setSlug( 'test' );
		$post->setExcerpt( 'Excerpt' );
		$post->setPublishedAt( new \DateTimeImmutable( '2024-01-01' ) );

		$repository = $this->createMock( IPostRepository::class );
		$repository
			->method( 'getPublished' )
			->willReturn( [$post] );

		$renderer = new WidgetRenderer( $repository );
		$result = $renderer->render( 'latest-posts', [] );

		// Check for proper HTML structure
		$this->assertStringContainsString( "<div class='latest-posts-widget'>", $result );
		$this->assertStringContainsString( "<h3 class='mb-4'>Latest Posts</h3>", $result );
		$this->assertStringContainsString( "<div class='post-list'>", $result );
		$this->assertStringContainsString( "<article class='post-item mb-4 pb-4 border-bottom'>", $result );
		$this->assertStringContainsString( "<h4 class='h5'>", $result );
		$this->assertStringContainsString( "<a href='/blog/article/test'", $result );
	}
}
