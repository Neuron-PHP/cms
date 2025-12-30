<?php

namespace Tests\Unit\Cms\Services\EventCategory;

use Neuron\Cms\Services\EventCategory\Updater;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
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

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		int $id,
		string $name,
		string $slug,
		string $color,
		?string $description = null
	): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/event-categories/update-event-category-request.yaml' );
		$dto = $factory->create();

		$dto->id = $id;
		$dto->name = $name;
		$dto->slug = $slug;
		$dto->color = $color;

		if( $description !== null )
		{
			$dto->description = $description;
		}

		return $dto;
	}

	public function test_update_basic_category(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setName( 'Old Name' );
		$category->setSlug( 'old-slug' );
		$category->setColor( '#000000' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'new-slug', 1 )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 1,
			name: 'New Name',
			slug: 'new-slug',
			color: '#ffffff'
		);

		$result = $this->updater->update( $dto );

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
			->method( 'findById' )
			->with( 5 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'conferences', 5 )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 5,
			name: 'Conferences',
			slug: 'conferences',
			color: '#ff0000',
			description: 'Professional conferences and summits'
		);

		$this->updater->update( $dto );

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
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'duplicate-slug', 1 )
			->willReturn( true );

		$this->categoryRepository->expects( $this->never() )
			->method( 'update' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'A category with this slug already exists' );

		$dto = $this->createDto(
			id: 1,
			name: 'New Name',
			slug: 'duplicate-slug',
			color: '#ffffff'
		);

		$this->updater->update( $dto );
	}

	public function test_update_allows_same_slug_for_same_category(): void
	{
		$category = new EventCategory();
		$category->setId( 3 );
		$category->setSlug( 'my-category' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 3 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-category', 3 )  // Should exclude category ID 3
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 3,
			name: 'Updated Name',
			slug: 'my-category',  // Same slug
			color: '#00ff00'
		);

		$this->updater->update( $dto );

		$this->assertEquals( 'my-category', $category->getSlug() );
	}

	public function test_update_changes_color(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setColor( '#000000' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 1,
			name: 'Test',
			slug: 'test',
			color: '#ff00ff'
		);

		$this->updater->update( $dto );

		$this->assertEquals( '#ff00ff', $category->getColor() );
	}

	public function test_update_clears_description_when_null(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setDescription( 'Old description' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $category )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 1,
			name: 'Test',
			slug: 'test',
			color: '#000000',
			description: null  // Clear description
		);

		$this->updater->update( $dto );

		$this->assertNull( $category->getDescription() );
	}

	public function test_update_calls_repository_update(): void
	{
		$category = new EventCategory();
		$category->setId( 7 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'findById' )
			->with( 7 )
			->willReturn( $category );

		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$this->categoryRepository->expects( $this->once() )
			->method( 'update' )
			->with( $this->identicalTo( $category ) )
			->willReturn( $category );

		$dto = $this->createDto(
			id: 7,
			name: 'Test',
			slug: 'test',
			color: '#000000'
		);

		$this->updater->update( $dto );
	}
}
