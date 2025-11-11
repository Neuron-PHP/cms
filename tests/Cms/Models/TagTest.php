<?php

namespace Tests\Cms\Models;

use DateTimeImmutable;
use Neuron\Cms\Models\Tag;
use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
	public function testCanCreateTag(): void
	{
		$tag = new Tag();
		$this->assertInstanceOf( Tag::class, $tag );
		$this->assertNull( $tag->getId() );
		$this->assertInstanceOf( DateTimeImmutable::class, $tag->getCreatedAt() );
	}

	public function testCanSetAndGetId(): void
	{
		$tag = new Tag();
		$tag->setId( 1 );
		$this->assertEquals( 1, $tag->getId() );
	}

	public function testCanSetAndGetName(): void
	{
		$tag = new Tag();
		$tag->setName( 'PHP' );
		$this->assertEquals( 'PHP', $tag->getName() );
	}

	public function testCanSetAndGetSlug(): void
	{
		$tag = new Tag();
		$tag->setSlug( 'php' );
		$this->assertEquals( 'php', $tag->getSlug() );
	}

	public function testCanSetAndGetCreatedAt(): void
	{
		$tag = new Tag();
		$date = new DateTimeImmutable( '2025-01-01 12:00:00' );
		$tag->setCreatedAt( $date );
		$this->assertEquals( $date, $tag->getCreatedAt() );
	}

	public function testCanSetAndGetUpdatedAt(): void
	{
		$tag = new Tag();
		$date = new DateTimeImmutable( '2025-01-02 12:00:00' );
		$tag->setUpdatedAt( $date );
		$this->assertEquals( $date, $tag->getUpdatedAt() );
	}

	public function testFromArray(): void
	{
		$data = [
			'id' => 1,
			'name' => 'PHP',
			'slug' => 'php',
			'created_at' => '2025-01-01 10:00:00',
			'updated_at' => '2025-01-01 11:00:00'
		];

		$tag = Tag::fromArray( $data );

		$this->assertEquals( 1, $tag->getId() );
		$this->assertEquals( 'PHP', $tag->getName() );
		$this->assertEquals( 'php', $tag->getSlug() );
		$this->assertInstanceOf( DateTimeImmutable::class, $tag->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $tag->getUpdatedAt() );
	}

	public function testToArray(): void
	{
		$tag = new Tag();
		$tag->setId( 1 );
		$tag->setName( 'PHP' );
		$tag->setSlug( 'php' );

		$array = $tag->toArray();

		$this->assertEquals( 1, $array['id'] );
		$this->assertEquals( 'PHP', $array['name'] );
		$this->assertEquals( 'php', $array['slug'] );
	}
}
