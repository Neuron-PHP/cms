<?php

namespace Tests\Cms\Services\Page;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Page\Creator;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;

class CreatorTest extends TestCase
{
	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		string $title,
		string $content,
		int $authorId,
		string $status,
		?string $slug = null,
		?string $template = null,
		?string $metaTitle = null,
		?string $metaDescription = null,
		?string $metaKeywords = null
	): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/pages/create-page-request.yaml' );
		$dto = $factory->create();

		$dto->title = $title;
		$dto->content = $content;
		$dto->author_id = $authorId;
		$dto->status = $status;

		if( $slug !== null )
		{
			$dto->slug = $slug;
		}
		if( $template !== null )
		{
			$dto->template = $template;
		}
		if( $metaTitle !== null )
		{
			$dto->meta_title = $metaTitle;
		}
		if( $metaDescription !== null )
		{
			$dto->meta_description = $metaDescription;
		}
		if( $metaKeywords !== null )
		{
			$dto->meta_keywords = $metaKeywords;
		}

		return $dto;
	}

	public function testCreatePageWithAllParameters(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$dto = $this->createDto(
			title: 'About Us',
			content: '{"blocks":[]}',
			authorId: 1,
			status: Page::STATUS_DRAFT,
			slug: 'about-us',
			template: 'custom',
			metaTitle: 'About Our Company',
			metaDescription: 'Learn about our company',
			metaKeywords: 'about, company'
		);

		$result = $creator->create( $dto );

		$this->assertInstanceOf( Page::class, $result );
		$this->assertEquals( 'About Us', $result->getTitle() );
		$this->assertEquals( 'about-us', $result->getSlug() );
		$this->assertEquals( '{"blocks":[]}', $result->getContentRaw() );
		$this->assertEquals( 'custom', $result->getTemplate() );
		$this->assertEquals( 'About Our Company', $result->getMetaTitle() );
		$this->assertEquals( 'Learn about our company', $result->getMetaDescription() );
		$this->assertEquals( 'about, company', $result->getMetaKeywords() );
		$this->assertEquals( 1, $result->getAuthorId() );
		$this->assertEquals( Page::STATUS_DRAFT, $result->getStatus() );
		$this->assertNotNull( $result->getCreatedAt() );
	}

	public function testCreatePageWithMinimalParameters(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$dto = $this->createDto(
			title: 'My Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_DRAFT
		);

		$result = $creator->create( $dto );

		$this->assertInstanceOf( Page::class, $result );
		$this->assertEquals( 'My Page', $result->getTitle() );
		$this->assertEquals( 'my-page', $result->getSlug() );  // Auto-generated from title
		$this->assertEquals( Page::TEMPLATE_DEFAULT, $result->getTemplate() );
	}

	public function testCreatePublishedPageSetsPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$dto = $this->createDto(
			title: 'Published Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_PUBLISHED
		);

		$result = $creator->create( $dto );

		$this->assertEquals( Page::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertNotNull( $result->getPublishedAt() );
	}

	public function testCreateDraftPageDoesNotSetPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$dto = $this->createDto(
			title: 'Draft Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_DRAFT
		);

		$result = $creator->create( $dto );

		$this->assertEquals( Page::STATUS_DRAFT, $result->getStatus() );
		$this->assertNull( $result->getPublishedAt() );
	}

	public function testSlugGenerationFromTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test various title formats
		$dto1 = $this->createDto( 'Hello World', '{}', 1, Page::STATUS_DRAFT );
		$result1 = $creator->create( $dto1 );
		$this->assertEquals( 'hello-world', $result1->getSlug() );

		$dto2 = $this->createDto( 'Multiple   Spaces', '{}', 1, Page::STATUS_DRAFT );
		$result2 = $creator->create( $dto2 );
		$this->assertEquals( 'multiple-spaces', $result2->getSlug() );

		$dto3 = $this->createDto( 'Special @#$ Characters!!!', '{}', 1, Page::STATUS_DRAFT );
		$result3 = $creator->create( $dto3 );
		$this->assertEquals( 'special-characters', $result3->getSlug() );

		$dto4 = $this->createDto( '  Leading and Trailing  ', '{}', 1, Page::STATUS_DRAFT );
		$result4 = $creator->create( $dto4 );
		$this->assertEquals( 'leading-and-trailing', $result4->getSlug() );
	}

	public function testSlugGenerationFallbackForNonAsciiTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test non-ASCII title - should generate fallback slug with pattern page-{uniqueid}
		$dto = $this->createDto( '你好', '{}', 1, Page::STATUS_DRAFT );
		$result = $creator->create( $dto );
		$this->assertMatchesRegularExpression( '/^page-[a-z0-9]+$/', $result->getSlug() );
	}

	public function testSlugGenerationForSymbolOnlyTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$creator = new Creator( $repository );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test symbols-only title - should generate fallback slug with pattern page-{uniqueid}
		$dto = $this->createDto( '@#$%^&*()', '{}', 1, Page::STATUS_DRAFT );
		$result = $creator->create( $dto );
		$this->assertMatchesRegularExpression( '/^page-[a-z0-9]+$/', $result->getSlug() );
	}
}
