<?php

namespace Tests\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\Creator;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
	private Creator $_creator;
	private ICategoryRepository $_mockCategoryRepository;

	protected function setUp(): void
	{
		$this->_mockCategoryRepository = $this->createMock( ICategoryRepository::class );

		$this->_creator = new Creator( $this->_mockCategoryRepository );
	}

	public function testCreatesCategory(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Category $category ) {
				return $category->getName() === 'Technology'
					&& $category->getSlug() === 'technology'
					&& $category->getDescription() === 'Tech articles';
			} ) )
			->willReturnArgument( 0 );

		$result = $this->_creator->create(
			'Technology',
			'technology',
			'Tech articles'
		);

		$this->assertEquals( 'Technology', $result->getName() );
		$this->assertEquals( 'technology', $result->getSlug() );
		$this->assertEquals( 'Tech articles', $result->getDescription() );
	}

	public function testGeneratesSlugWhenEmpty(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Category $category ) {
				return $category->getSlug() === 'programming-tutorials';
			} ) )
			->willReturnArgument( 0 );

		$result = $this->_creator->create(
			'Programming Tutorials',
			'',
			'Learn programming'
		);

		$this->assertEquals( 'programming-tutorials', $result->getSlug() );
	}

	public function testHandlesSpecialCharactersInSlug(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Category $category ) {
				return $category->getSlug() === 'web-development';
			} ) )
			->willReturnArgument( 0 );

		$result = $this->_creator->create(
			'Web & Development!',
			'',
			''
		);

		$this->assertEquals( 'web-development', $result->getSlug() );
	}

	public function testUsesCustomSlug(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Category $category ) {
				return $category->getSlug() === 'custom-slug';
			} ) )
			->willReturnArgument( 0 );

		$result = $this->_creator->create(
			'Category Name',
			'custom-slug',
			''
		);

		$this->assertEquals( 'custom-slug', $result->getSlug() );
	}

	public function testAllowsEmptyDescription(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Category $category ) {
				return $category->getDescription() === '';
			} ) )
			->willReturnArgument( 0 );

		$result = $this->_creator->create(
			'Category',
			'category',
			''
		);

		$this->assertEquals( '', $result->getDescription() );
	}
}
