<?php

namespace Tests\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\Creator;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
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

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		string $name,
		?string $slug = null,
		?string $description = null
	): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../config/dtos/categories/create-category-request.yaml' );
		$dto = $factory->create();

		$dto->name = $name;

		if( $slug !== null )
		{
			$dto->slug = $slug;
		}
		if( $description !== null )
		{
			$dto->description = $description;
		}

		return $dto;
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

		$dto = $this->createDto(
			name: 'Technology',
			slug: 'technology',
			description: 'Tech articles'
		);

		$result = $this->_creator->create( $dto );

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

		$dto = $this->createDto(
			name: 'Programming Tutorials',
			slug: '',
			description: 'Learn programming'
		);

		$result = $this->_creator->create( $dto );

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

		$dto = $this->createDto(
			name: 'Web & Development!',
			slug: '',
			description: ''
		);

		$result = $this->_creator->create( $dto );

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

		$dto = $this->createDto(
			name: 'Category Name',
			slug: 'custom-slug',
			description: ''
		);

		$result = $this->_creator->create( $dto );

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

		$dto = $this->createDto(
			name: 'Category',
			slug: 'category',
			description: ''
		);

		$result = $this->_creator->create( $dto );

		$this->assertEquals( '', $result->getDescription() );
	}
}
