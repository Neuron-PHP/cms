<?php

namespace Tests\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\Deleter;
use PHPUnit\Framework\TestCase;

class DeleterTest extends TestCase
{
	private Deleter $_deleter;
	private ICategoryRepository $_mockCategoryRepository;

	protected function setUp(): void
	{
		$this->_mockCategoryRepository = $this->createMock( ICategoryRepository::class );

		$this->_deleter = new Deleter( $this->_mockCategoryRepository );
	}

	public function testDeletesCategoryById(): void
	{
		$category = new Category();
		$category->setId( 3 );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 3 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 3 )
			->willReturn( true );

		$result = $this->_deleter->delete( 3 );

		$this->assertTrue( $result );
	}

	public function testDeletesMultipleCategories(): void
	{
		$category1 = new Category();
		$category1->setId( 1 );
		$category2 = new Category();
		$category2->setId( 5 );
		$category3 = new Category();
		$category3->setId( 10 );

		$this->_mockCategoryRepository
			->expects( $this->exactly( 3 ) )
			->method( 'findById' )
			->withConsecutive( [ 1 ], [ 5 ], [ 10 ] )
			->willReturnOnConsecutiveCalls( $category1, $category2, $category3 );

		$this->_mockCategoryRepository
			->expects( $this->exactly( 3 ) )
			->method( 'delete' )
			->withConsecutive( [ 1 ], [ 5 ], [ 10 ] )
			->willReturn( true );

		$this->_deleter->delete( 1 );
		$this->_deleter->delete( 5 );
		$this->_deleter->delete( 10 );
	}

	public function testThrowsExceptionWhenCategoryNotFound(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 99 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Category not found' );

		$this->_deleter->delete( 99 );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$categoryRepository = $this->createMock( ICategoryRepository::class );

		$deleter = new Deleter( $categoryRepository );

		$this->assertInstanceOf( Deleter::class, $deleter );
	}

	public function testConstructorWithEventEmitter(): void
	{
		$categoryRepository = $this->createMock( ICategoryRepository::class );
		$eventEmitter = $this->createMock( \Neuron\Events\Emitter::class );

		$category = new Category();
		$category->setId( 1 );

		$categoryRepository
			->method( 'findById' )
			->willReturn( $category );

		$categoryRepository
			->method( 'delete' )
			->willReturn( true );

		// Event emitter should emit CategoryDeletedEvent
		$eventEmitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\CategoryDeletedEvent::class ) );

		$deleter = new Deleter( $categoryRepository, $eventEmitter );

		$deleter->delete( 1 );
	}
}
