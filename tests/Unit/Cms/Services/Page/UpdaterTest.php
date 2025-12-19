<?php

namespace Tests\Cms\Services\Page;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Page\Updater;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;

class UpdaterTest extends TestCase
{
	public function testUpdatePageWithAllParameters(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );
		$page->setSlug( 'old-slug' );
		$page->setStatus( Page::STATUS_DRAFT );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->with( $page )
			->willReturn( true );

		$result = $updater->update(
			page: $page,
			title: 'Updated Title',
			content: '{"blocks":[]}',
			status: Page::STATUS_DRAFT,
			slug: 'new-slug',
			template: 'custom',
			metaTitle: 'Updated Meta Title',
			metaDescription: 'Updated meta description',
			metaKeywords: 'updated, keywords'
		);

		$this->assertTrue( $result );
		$this->assertEquals( 'Updated Title', $page->getTitle() );
		$this->assertEquals( 'new-slug', $page->getSlug() );
		$this->assertEquals( '{"blocks":[]}', $page->getContentRaw() );
		$this->assertEquals( 'custom', $page->getTemplate() );
		$this->assertEquals( 'Updated Meta Title', $page->getMetaTitle() );
		$this->assertEquals( 'Updated meta description', $page->getMetaDescription() );
		$this->assertEquals( 'updated, keywords', $page->getMetaKeywords() );
		$this->assertNotNull( $page->getUpdatedAt() );
	}

	public function testUpdatePageWithoutSlugKeepsExistingSlug(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );
		$page->setSlug( 'original-slug' );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( true );

		$result = $updater->update(
			page: $page,
			title: 'Updated Title',
			content: '{}',
			status: Page::STATUS_DRAFT,
			slug: null  // Not providing new slug
		);

		$this->assertTrue( $result );
		$this->assertEquals( 'original-slug', $page->getSlug() );
	}

	public function testUpdateToPublishedSetsPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );
		$page->setStatus( Page::STATUS_DRAFT );
		// Not yet published
		$this->assertNull( $page->getPublishedAt() );

		$repository
			->method( 'update' )
			->willReturn( true );

		$updater->update(
			page: $page,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_PUBLISHED
		);

		$this->assertEquals( Page::STATUS_PUBLISHED, $page->getStatus() );
		$this->assertNotNull( $page->getPublishedAt() );
	}

	public function testUpdateAlreadyPublishedPageKeepsOriginalPublishedDate(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );
		$page->setStatus( Page::STATUS_PUBLISHED );
		$originalPublishedDate = new \DateTimeImmutable( '2024-01-01 00:00:00' );
		$page->setPublishedAt( $originalPublishedDate );

		$repository
			->method( 'update' )
			->willReturn( true );

		$updater->update(
			page: $page,
			title: 'Updated Title',
			content: '{}',
			status: Page::STATUS_PUBLISHED
		);

		// Published date should remain the original
		$this->assertEquals( $originalPublishedDate, $page->getPublishedAt() );
	}

	public function testUpdateSetsUpdatedAtTimestamp(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );

		$repository
			->method( 'update' )
			->willReturn( true );

		$updater->update(
			page: $page,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_DRAFT
		);

		$this->assertNotNull( $page->getUpdatedAt() );
		$this->assertInstanceOf( \DateTimeImmutable::class, $page->getUpdatedAt() );
	}

	public function testUpdateWhenRepositoryFails(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$page = new Page();
		$page->setId( 1 );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( false );

		$result = $updater->update(
			page: $page,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_DRAFT
		);

		$this->assertFalse( $result );
	}
}
