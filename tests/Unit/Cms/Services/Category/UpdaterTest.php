<?php

namespace Tests\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\Updater;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
	private Updater $_updater;
	private ICategoryRepository $_mockCategoryRepository;

	protected function setUp(): void
	{
		$this->_mockCategoryRepository = $this->createMock( ICategoryRepository::class );

		$this->_updater = new Updater( $this->_mockCategoryRepository );
	}

	/**
	 * Helper method to create a DTO with test data
	 */
	private function createDto(
		int $id,
		string $name,
		?string $slug = null,
		?string $description = null
	): Dto
	{
		$factory = new Factory( __DIR__ . "/../../../../../src/Cms/Dtos/categories/update-category-request.yaml" );
		$dto = $factory->create();

		$dto->id = $id;
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

	public function testUpdatesCategory(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setName( 'Old Name' );
		$category->setSlug( 'old-slug' );
		$category->setDescription( 'Old description' );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getName() === 'New Name'
					&& $c->getSlug() === 'new-slug'
					&& $c->getDescription() === 'New description';
			} ) );

		$dto = $this->createDto(
			id: 1,
			name: 'New Name',
			slug: 'new-slug',
			description: 'New description'
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'New Name', $result->getName() );
		$this->assertEquals( 'new-slug', $result->getSlug() );
		$this->assertEquals( 'New description', $result->getDescription() );
	}

	public function testGeneratesSlugWhenEmpty(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setName( 'Old Name' );
		$category->setSlug( 'old-slug' );

		$this->_mockCategoryRepository
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'technology-news';
			} ) );

		$dto = $this->createDto(
			id: 1,
			name: 'Technology News',
			slug: '',
			description: 'Tech updates'
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'technology-news', $result->getSlug() );
	}

	public function testHandlesSpecialCharacters(): void
	{
		$category = new Category();
		$category->setId( 1 );

		$this->_mockCategoryRepository
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'tips-tricks';
			} ) );

		$dto = $this->createDto(
			id: 1,
			name: 'Tips & Tricks!',
			slug: '',
			description: ''
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'tips-tricks', $result->getSlug() );
	}

	public function testUsesCustomSlug(): void
	{
		$category = new Category();
		$category->setId( 1 );

		$this->_mockCategoryRepository
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'my-custom-slug';
			} ) );

		$dto = $this->createDto(
			id: 1,
			name: 'Category Name',
			slug: 'my-custom-slug',
			description: ''
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( 'my-custom-slug', $result->getSlug() );
	}

	public function testAllowsEmptyDescription(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setDescription( 'Old description' );

		$this->_mockCategoryRepository
			->method( 'findById' )
			->with( 1 )
			->willReturn( $category );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getDescription() === '';
			} ) );

		$dto = $this->createDto(
			id: 1,
			name: 'Name',
			slug: 'slug',
			description: ''
		);

		$result = $this->_updater->update( $dto );

		$this->assertEquals( '', $result->getDescription() );
	}

	public function testConstructorSetsPropertiesCorrectly(): void
	{
		$categoryRepository = $this->createMock( ICategoryRepository::class );

		$updater = new Updater( $categoryRepository );

		$this->assertInstanceOf( Updater::class, $updater );
	}

	public function testConstructorWithEventEmitter(): void
	{
		$categoryRepository = $this->createMock( ICategoryRepository::class );
		$eventEmitter = $this->createMock( \Neuron\Events\Emitter::class );

		$category = new Category();
		$category->setId( 1 );
		$category->setName( 'Test' );

		$categoryRepository
			->method( 'findById' )
			->willReturn( $category );

		$categoryRepository
			->method( 'update' );

		// Event emitter should emit CategoryUpdatedEvent
		$eventEmitter
			->expects( $this->once() )
			->method( 'emit' )
			->with( $this->isInstanceOf( \Neuron\Cms\Events\CategoryUpdatedEvent::class ) );

		$updater = new Updater( $categoryRepository, null, $eventEmitter );

		$dto = $this->createDto(
			id: 1,
			name: 'Updated Name',
			slug: 'updated-slug'
		);

		$updater->update( $dto );
	}

	public function testThrowsExceptionWhenCategoryNotFound(): void
	{
		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'findById' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Category with ID 999 not found' );

		$dto = $this->createDto(
			id: 999,
			name: 'Test',
			slug: 'test'
		);

		$this->_updater->update( $dto );
	}
}
