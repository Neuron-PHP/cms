<?php

namespace Tests\Unit\Cms\Services\EventCategory;

use Neuron\Cms\Services\EventCategory\Creator;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
	private Creator $creator;
	private $categoryRepository;

	protected function setUp(): void
	{
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$this->creator = new Creator( $this->categoryRepository );
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		string $name,
		?string $slug = null,
		?string $color = null,
		?string $description = null
	): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/event-categories/create-event-category-request.yaml' );
		$dto = $factory->create();

		$dto->name = $name;

		if( $slug !== null )
		{
			$dto->slug = $slug;
		}
		if( $color !== null )
		{
			$dto->color = $color;
		}
		if( $description !== null )
		{
			$dto->description = $description;
		}

		return $dto;
	}

	public function test_create_basic_category(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'workshops' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto( name: 'Workshops' );

		$result = $this->creator->create( $dto );

		$this->assertInstanceOf( EventCategory::class, $result );
		$this->assertEquals( 'Workshops', $capturedCategory->getName() );
		$this->assertEquals( 'workshops', $capturedCategory->getSlug() );
		$this->assertEquals( '#3b82f6', $capturedCategory->getColor() ); // Default color
		$this->assertNull( $capturedCategory->getDescription() );
	}

	public function test_create_with_custom_slug(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'custom-slug' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto(
			name: 'Workshops',
			slug: 'custom-slug'
		);

		$this->creator->create( $dto );

		$this->assertEquals( 'custom-slug', $capturedCategory->getSlug() );
	}

	public function test_create_with_all_fields(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'tech-events' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto(
			name: 'Tech Events',
			slug: 'tech-events',
			color: '#ff0000',
			description: 'Technology related events and conferences'
		);

		$this->creator->create( $dto );

		$this->assertEquals( 'Tech Events', $capturedCategory->getName() );
		$this->assertEquals( 'tech-events', $capturedCategory->getSlug() );
		$this->assertEquals( '#ff0000', $capturedCategory->getColor() );
		$this->assertEquals( 'Technology related events and conferences', $capturedCategory->getDescription() );
	}

	public function test_create_generates_slug_from_name(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'my-awesome-category' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto( name: 'My Awesome Category!!!' );

		$this->creator->create( $dto );

		$this->assertEquals( 'my-awesome-category', $capturedCategory->getSlug() );
	}

	public function test_create_handles_non_ascii_name(): void
	{
		// For non-ASCII names, the slug generator creates a unique ID
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( $this->stringStartsWith( 'category-' ) )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto( name: '日本語' );

		$this->creator->create( $dto );

		$this->assertEquals( '日本語', $capturedCategory->getName() );
		$this->assertStringStartsWith( 'category-', $capturedCategory->getSlug() );
	}

	public function test_create_throws_exception_when_slug_exists(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'duplicate-slug' )
			->willReturn( true );

		$this->categoryRepository->expects( $this->never() )
			->method( 'create' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'A category with this slug already exists' );

		$dto = $this->createDto(
			name: 'Test Category',
			slug: 'duplicate-slug'
		);

		$this->creator->create( $dto );
	}

	public function test_create_uses_default_color_when_not_provided(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto( name: 'Test Category' );

		$this->creator->create( $dto );

		$this->assertEquals( '#3b82f6', $capturedCategory->getColor() );
	}

	public function test_create_with_custom_color(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto(
			name: 'Test Category',
			color: '#00ff00'
		);

		$this->creator->create( $dto );

		$this->assertEquals( '#00ff00', $capturedCategory->getColor() );
	}

	public function test_create_normalizes_slug_characters(): void
	{
		$this->categoryRepository->expects( $this->once() )
			->method( 'slugExists' )
			->with( 'test-category-123' )
			->willReturn( false );

		$capturedCategory = null;
		$this->categoryRepository->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( EventCategory $category ) use ( &$capturedCategory ) {
				$capturedCategory = $category;
				return true;
			}))
			->willReturnCallback( function( EventCategory $category ) {
				$category->setId( 1 );
				return $category;
			});

		$dto = $this->createDto( name: 'Test @Category #123!' );

		$this->creator->create( $dto );

		$this->assertEquals( 'test-category-123', $capturedCategory->getSlug() );
	}
}
