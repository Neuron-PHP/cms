<?php

namespace Tests\Unit\Cms\Services\EventCategory;

use Neuron\Cms\Services\EventCategory\Creator;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
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

		$result = $this->creator->create( 'Workshops' );

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

		$this->creator->create(
			'Workshops',
			'custom-slug'
		);

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

		$this->creator->create(
			'Tech Events',
			'tech-events',
			'#ff0000',
			'Technology related events and conferences'
		);

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

		$this->creator->create( 'My Awesome Category!!!' );

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

		$this->creator->create( '日本語' );

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

		$this->creator->create(
			'Test Category',
			'duplicate-slug'
		);
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

		$this->creator->create( 'Test Category' );

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

		$this->creator->create(
			'Test Category',
			null,
			'#00ff00'
		);

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

		$this->creator->create( 'Test @Category #123!' );

		$this->assertEquals( 'test-category-123', $capturedCategory->getSlug() );
	}
}
