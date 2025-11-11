<?php

namespace Tests\Cms\Services\Category;

use Neuron\Cms\Models\Category;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Services\Category\Updater;
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

	public function testUpdatesCategory(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setName( 'Old Name' );
		$category->setSlug( 'old-slug' );
		$category->setDescription( 'Old description' );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getName() === 'New Name'
					&& $c->getSlug() === 'new-slug'
					&& $c->getDescription() === 'New description';
			} ) );

		$result = $this->_updater->update(
			$category,
			'New Name',
			'new-slug',
			'New description'
		);

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
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'technology-news';
			} ) );

		$result = $this->_updater->update(
			$category,
			'Technology News',
			'',
			'Tech updates'
		);

		$this->assertEquals( 'technology-news', $result->getSlug() );
	}

	public function testHandlesSpecialCharacters(): void
	{
		$category = new Category();
		$category->setId( 1 );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'tips-tricks';
			} ) );

		$result = $this->_updater->update(
			$category,
			'Tips & Tricks!',
			'',
			''
		);

		$this->assertEquals( 'tips-tricks', $result->getSlug() );
	}

	public function testUsesCustomSlug(): void
	{
		$category = new Category();
		$category->setId( 1 );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getSlug() === 'my-custom-slug';
			} ) );

		$result = $this->_updater->update(
			$category,
			'Category Name',
			'my-custom-slug',
			''
		);

		$this->assertEquals( 'my-custom-slug', $result->getSlug() );
	}

	public function testAllowsEmptyDescription(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setDescription( 'Old description' );

		$this->_mockCategoryRepository
			->expects( $this->once() )
			->method( 'update' )
			->with( $this->callback( function( Category $c ) {
				return $c->getDescription() === '';
			} ) );

		$result = $this->_updater->update(
			$category,
			'Name',
			'slug',
			''
		);

		$this->assertEquals( '', $result->getDescription() );
	}
}
