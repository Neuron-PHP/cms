<?php

namespace Tests\Unit\Cms\Services\EventCategory;

use Neuron\Cms\Services\EventCategory\Deleter;
use Neuron\Cms\Models\EventCategory;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use PHPUnit\Framework\TestCase;

class DeleterTest extends TestCase
{
	private Deleter $deleter;
	private $categoryRepository;

	protected function setUp(): void
	{
		$this->categoryRepository = $this->createMock( IEventCategoryRepository::class );

		$this->deleter = new Deleter( $this->categoryRepository );
	}

	public function test_delete_returns_true_on_success(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );
		$category->setName( 'Category to Delete' );

		$this->categoryRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $category )
			->willReturn( true );

		$result = $this->deleter->delete( $category );

		$this->assertTrue( $result );
	}

	public function test_delete_returns_false_on_failure(): void
	{
		$category = new EventCategory();
		$category->setId( 1 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $category )
			->willReturn( false );

		$result = $this->deleter->delete( $category );

		$this->assertFalse( $result );
	}

	public function test_delete_calls_repository_delete(): void
	{
		$category = new EventCategory();
		$category->setId( 5 );

		$this->categoryRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $this->identicalTo( $category ) )
			->willReturn( true );

		$this->deleter->delete( $category );
	}
}
