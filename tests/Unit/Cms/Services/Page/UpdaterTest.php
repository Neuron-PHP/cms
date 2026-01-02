<?php

namespace Tests\Cms\Services\Page;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Page\Updater;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;

class UpdaterTest extends TestCase
{
	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		int $id,
		string $title,
		string $content,
		string $status,
		?string $slug = null,
		?string $template = null,
		?string $metaTitle = null,
		?string $metaDescription = null,
		?string $metaKeywords = null
	): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/pages/update-page-request.yaml" );
		$dto = $factory->create();

		$dto->id = $id;
		$dto->title = $title;
		$dto->content = $content;
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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->with( $page )
			->willReturn( true );

		$dto = $this->createDto(
			id: 1,
			title: 'Updated Title',
			content: '{"blocks":[]}',
			status: Page::STATUS_DRAFT,
			slug: 'new-slug',
			template: 'custom',
			metaTitle: 'Updated Meta Title',
			metaDescription: 'Updated meta description',
			metaKeywords: 'updated, keywords'
		);

		$result = $updater->update( $dto );

		$this->assertInstanceOf( Page::class, $result );
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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( true );

		$dto = $this->createDto(
			id: 1,
			title: 'Updated Title',
			content: '{}',
			status: Page::STATUS_DRAFT,
			slug: null  // Not providing new slug
		);

		$result = $updater->update( $dto );

		$this->assertInstanceOf( Page::class, $result );
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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->method( 'update' )
			->willReturn( true );

		$dto = $this->createDto(
			id: 1,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_PUBLISHED
		);

		$updater->update( $dto );

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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->method( 'update' )
			->willReturn( true );

		$dto = $this->createDto(
			id: 1,
			title: 'Updated Title',
			content: '{}',
			status: Page::STATUS_PUBLISHED
		);

		$updater->update( $dto );

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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->method( 'update' )
			->willReturn( true );

		$dto = $this->createDto(
			id: 1,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_DRAFT
		);

		$updater->update( $dto );

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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $page );

		$repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturn( false );

		$dto = $this->createDto(
			id: 1,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_DRAFT
		);

		$result = $updater->update( $dto );

		$this->assertInstanceOf( Page::class, $result );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$repository = $this->createMock( IPageRepository::class );

		$updater = new Updater( $repository );

		$this->assertInstanceOf( Updater::class, $updater );
	}

	public function testUpdateThrowsExceptionWhenPageNotFound(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$updater = new Updater( $repository );

		$repository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Page with ID 999 not found' );

		$dto = $this->createDto(
			id: 999,
			title: 'Title',
			content: '{}',
			status: Page::STATUS_DRAFT
		);

		$updater->update( $dto );
	}
}
