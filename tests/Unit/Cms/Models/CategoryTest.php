<?php

namespace Tests\Cms\Models;

use DateTimeImmutable;
use Neuron\Cms\Models\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
	public function testCanCreateCategory(): void
	{
		$category = new Category();
		$this->assertInstanceOf( Category::class, $category );
		$this->assertNull( $category->getId() );
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getCreatedAt() );
	}

	public function testCanSetAndGetId(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$this->assertEquals( 1, $category->getId() );
	}

	public function testCanSetAndGetName(): void
	{
		$category = new Category();
		$category->setName( 'Technology' );
		$this->assertEquals( 'Technology', $category->getName() );
	}

	public function testCanSetAndGetSlug(): void
	{
		$category = new Category();
		$category->setSlug( 'technology' );
		$this->assertEquals( 'technology', $category->getSlug() );
	}

	public function testCanSetAndGetDescription(): void
	{
		$category = new Category();
		$category->setDescription( 'Tech articles' );
		$this->assertEquals( 'Tech articles', $category->getDescription() );
	}

	public function testCanSetAndGetCreatedAt(): void
	{
		$category = new Category();
		$date = new DateTimeImmutable( '2025-01-01 12:00:00' );
		$category->setCreatedAt( $date );
		$this->assertEquals( $date, $category->getCreatedAt() );
	}

	public function testCanSetAndGetUpdatedAt(): void
	{
		$category = new Category();
		$date = new DateTimeImmutable( '2025-01-02 12:00:00' );
		$category->setUpdatedAt( $date );
		$this->assertEquals( $date, $category->getUpdatedAt() );
	}

	public function testFromArray(): void
	{
		$data = [
			'id' => 1,
			'name' => 'Technology',
			'slug' => 'technology',
			'description' => 'Tech articles',
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-01 11:00:00'
		];

		$category = Category::fromArray( $data );

		$this->assertEquals( 1, $category->getId() );
		$this->assertEquals( 'Technology', $category->getName() );
		$this->assertEquals( 'technology', $category->getSlug() );
		$this->assertEquals( 'Tech articles', $category->getDescription() );
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $category->getUpdatedAt() );
	}

	public function testToArray(): void
	{
		$category = new Category();
		$category->setId( 1 );
		$category->setName( 'Technology' );
		$category->setSlug( 'technology' );
		$category->setDescription( 'Tech articles' );

		$array = $category->toArray();

		$this->assertEquals( 1, $array['id'] );
		$this->assertEquals( 'Technology', $array['name'] );
		$this->assertEquals( 'technology', $array['slug'] );
		$this->assertEquals( 'Tech articles', $array['description'] );
	}

	public function testFromArrayWithNullDescription(): void
	{
		$data = [
			'id' => 1,
			'name' => 'Technology',
			'slug' => 'technology',
			'description' => null
		];

		$category = Category::fromArray( $data );

		$this->assertNull( $category->getDescription() );
	}
}
