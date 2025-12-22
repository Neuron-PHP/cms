<?php

namespace Tests\Unit\Cms\Services\EventCategory;

use Neuron\Cms\Services\EventCategory\Updater;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
	private Updater $updater;
	private $categoryRepository;

	protected function setUp(): void
	{
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$this->updater = new Updater( $this->categoryRepository );
	}

	public function test_update_basic_category(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setName( 'Old Name' );
		$category->setSlug( 'old-slug' );
		$category->setColor( '#000000' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'new-slug', 1 )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$result = $this->updater->update(
			$category,
			'New Name',
			'new-slug',
			'#ffffff'
		);

		$this->assertInstanceOf( EventCategory::class, $result );
		$this->assertEquals( 'New Name', $category->getName() );
		$this->assertEquals( 'new-slug', $category->getSlug() );
		$this->assertEquals( '#ffffff', $category->getColor() );
		$this->assertNull( $category->getDescription() );
	}

	public function test_update_with_description(): void
	{
		$category = new EventCategory();
		$category->setId( 5 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'conferences', 5 )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$this->updater->update(
			$category,
			'Conferences',
			'conferences',
			'#ff0000',
			'Professional conferences and summits'
		);

		$this->assertEquals( 'Conferences', $category->getName() );
		$this->assertEquals( 'conferences', $category->getSlug() );
		$this->assertEquals( '#ff0000', $category->getColor() );
		$this->assertEquals( 'Professional conferences and summits', $category->getDescription() );
	}

	public function test_update_throws_exception_when_slug_exists_for_other_category(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'duplicate-slug', 1 )
			->willReturn( true );

		$this->categoryRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'A category with this slug already exists' );

		$this->updater->update(
			$category,
			'New Name',
			'duplicate-slug',
			'#ffffff'
		);
	}

	public function test_update_allows_same_slug_for_same_category(): void
	{
		$category = new EventCategory();
		$category->setId( 3 );
		$category->setSlug( 'my-category' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-category', 3 )  // Should exclude category ID 3
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$this->updater->update(
			$category,
			'Updated Name',
			'my-category',  // Same slug
			'#00ff00'
		);

		$this->assertEquals( 'my-category', $category->getSlug() );
	}

	public function test_update_changes_color(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setColor( '#000000' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$this->updater->update(
			$category,
			'Test',
			'test',
			'#ff00ff'
		);

		$this->assertEquals( '#ff00ff', $category->getColor() );
	}

	public function test_update_clears_description_when_null(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setDescription( 'Old description' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$this->updater->update(
			$category,
			'Test',
			'test',
			'#000000',
			null  // Clear description
		);

		$this->assertNull( $category->getDescription() );
	}

	public function test_update_calls_repository_update(): void
	{
		$category = new EventCategory();
		$category->setId( 7 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $this->identicalTo( $category ) )
			->willReturn( $category );

		$this->updater->update(
			$category,
			'Test',
			'test',
			'#000000'
		);
	}
}
