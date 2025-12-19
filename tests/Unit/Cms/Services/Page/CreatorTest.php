<?php

namespace Tests\Cms\Services\Page;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Page\Creator;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Core\System\IRandom;

class CreatorTest extends TestCase
{
	public function testCreatePageWithAllParameters(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		$creator = new Creator( $repository, $random );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$result = $creator->create(
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
		$random = $this->createMock( IRandom::class );

		$creator = new Creator( $repository, $random );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$result = $creator->create(
			title: 'My Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_DRAFT
		);

		$this->assertInstanceOf( Page::class, $result );
		$this->assertEquals( 'My Page', $result->getTitle() );
		$this->assertEquals( 'my-page', $result->getSlug() );  // Auto-generated from title
		$this->assertEquals( Page::TEMPLATE_DEFAULT, $result->getTemplate() );
	}

	public function testCreatePublishedPageSetsPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		$creator = new Creator( $repository, $random );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$result = $creator->create(
			title: 'Published Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_PUBLISHED
		);

		$this->assertEquals( Page::STATUS_PUBLISHED, $result->getStatus() );
		$this->assertNotNull( $result->getPublishedAt() );
	}

	public function testCreateDraftPageDoesNotSetPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		$creator = new Creator( $repository, $random );

		// Configure repository to return the page it receives
		$repository
			->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				$page->setId( 1 );
				return $page;
			} );

		$result = $creator->create(
			title: 'Draft Page',
			content: '{}',
			authorId: 1,
			status: Page::STATUS_DRAFT
		);

		$this->assertEquals( Page::STATUS_DRAFT, $result->getStatus() );
		$this->assertNull( $result->getPublishedAt() );
	}

	public function testSlugGenerationFromTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		$creator = new Creator( $repository, $random );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test various title formats
		$result1 = $creator->create( 'Hello World', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'hello-world', $result1->getSlug() );

		$result2 = $creator->create( 'Multiple   Spaces', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'multiple-spaces', $result2->getSlug() );

		$result3 = $creator->create( 'Special @#$ Characters!!!', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'special-characters', $result3->getSlug() );

		$result4 = $creator->create( '  Leading and Trailing  ', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'leading-and-trailing', $result4->getSlug() );
	}

	public function testSlugGenerationFallbackForNonAsciiTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		// Mock random to return predictable unique ID
		$random
			->expects( $this->once() )
			->method( 'uniqueId' )
			->willReturn( 'abc123' );

		$creator = new Creator( $repository, $random );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test non-ASCII title
		$result = $creator->create( '你好', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'page-abc123', $result->getSlug() );
	}

	public function testSlugGenerationForSymbolOnlyTitle(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$random = $this->createMock( IRandom::class );

		// Mock random to return predictable unique ID
		$random
			->expects( $this->once() )
			->method( 'uniqueId' )
			->willReturn( 'xyz789' );

		$creator = new Creator( $repository, $random );

		$repository
			->method( 'create' )
			->willReturnCallback( function( Page $page ) {
				return $page;
			} );

		// Test symbols-only title
		$result = $creator->create( '@#$%^&*()', '{}', 1, Page::STATUS_DRAFT );
		$this->assertEquals( 'page-xyz789', $result->getSlug() );
	}
}
