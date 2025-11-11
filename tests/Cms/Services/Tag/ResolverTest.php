<?php

namespace Tests\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Tag\Creator;
use Neuron\Cms\Services\Tag\Resolver;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
	private Resolver $_resolver;
	private ITagRepository $_mockRepository;
	private Creator $_mockCreator;

	protected function setUp(): void
	{
		$this->_mockRepository = $this->createMock( ITagRepository::class );
		$this->_mockCreator = $this->createMock( Creator::class );
		$this->_resolver = new Resolver( $this->_mockRepository, $this->_mockCreator );
	}

	public function testReturnsEmptyArrayForEmptyString(): void
	{
		$result = $this->_resolver->resolveFromString( '' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testResolvesExistingTag(): void
	{
		$existingTag = new Tag();
		$existingTag->setId( 1 );
		$existingTag->setName( 'PHP' );
		$existingTag->setSlug( 'php' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'findByName' )
			->with( 'PHP' )
			->willReturn( $existingTag );

		$this->_mockCreator
			->expects( $this->never() )
			->method( 'create' );

		$result = $this->_resolver->resolveFromString( 'PHP' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'PHP', $result[0]->getName() );
	}

	public function testCreatesNonExistingTag(): void
	{
		$newTag = new Tag();
		$newTag->setId( 1 );
		$newTag->setName( 'NewTag' );
		$newTag->setSlug( 'newtag' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'findByName' )
			->with( 'NewTag' )
			->willReturn( null );

		$this->_mockCreator
			->expects( $this->once() )
			->method( 'create' )
			->with( 'NewTag' )
			->willReturn( $newTag );

		$result = $this->_resolver->resolveFromString( 'NewTag' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'NewTag', $result[0]->getName() );
	}

	public function testResolvesMultipleTagsFromCommaSeparatedString(): void
	{
		$tag1 = new Tag();
		$tag1->setId( 1 );
		$tag1->setName( 'PHP' );

		$tag2 = new Tag();
		$tag2->setId( 2 );
		$tag2->setName( 'JavaScript' );

		$tag3 = new Tag();
		$tag3->setId( 3 );
		$tag3->setName( 'Python' );

		$this->_mockRepository
			->expects( $this->exactly( 3 ) )
			->method( 'findByName' )
			->willReturnCallback( function( $name ) use ( $tag1, $tag2, $tag3 ) {
				return match( $name ) {
					'PHP' => $tag1,
					'JavaScript' => $tag2,
					'Python' => $tag3,
					default => null
				};
			} );

		$result = $this->_resolver->resolveFromString( 'PHP, JavaScript, Python' );

		$this->assertCount( 3, $result );
		$this->assertEquals( 'PHP', $result[0]->getName() );
		$this->assertEquals( 'JavaScript', $result[1]->getName() );
		$this->assertEquals( 'Python', $result[2]->getName() );
	}

	public function testTrimsWhitespaceFromTagNames(): void
	{
		$tag = new Tag();
		$tag->setId( 1 );
		$tag->setName( 'PHP' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'findByName' )
			->with( 'PHP' )
			->willReturn( $tag );

		$result = $this->_resolver->resolveFromString( '   PHP   ' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'PHP', $result[0]->getName() );
	}

	public function testSkipsEmptyTagNames(): void
	{
		$tag1 = new Tag();
		$tag1->setId( 1 );
		$tag1->setName( 'PHP' );

		$tag2 = new Tag();
		$tag2->setId( 2 );
		$tag2->setName( 'JavaScript' );

		$this->_mockRepository
			->expects( $this->exactly( 2 ) )
			->method( 'findByName' )
			->willReturnCallback( function( $name ) use ( $tag1, $tag2 ) {
				return match( $name ) {
					'PHP' => $tag1,
					'JavaScript' => $tag2,
					default => null
				};
			} );

		// Multiple commas and spaces should result in only 2 tags
		$result = $this->_resolver->resolveFromString( 'PHP, , ,JavaScript' );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'PHP', $result[0]->getName() );
		$this->assertEquals( 'JavaScript', $result[1]->getName() );
	}

	public function testMixOfExistingAndNewTags(): void
	{
		$existingTag = new Tag();
		$existingTag->setId( 1 );
		$existingTag->setName( 'PHP' );

		$newTag = new Tag();
		$newTag->setId( 2 );
		$newTag->setName( 'Rust' );

		$this->_mockRepository
			->expects( $this->exactly( 2 ) )
			->method( 'findByName' )
			->willReturnCallback( function( $name ) use ( $existingTag ) {
				return $name === 'PHP' ? $existingTag : null;
			} );

		$this->_mockCreator
			->expects( $this->once() )
			->method( 'create' )
			->with( 'Rust' )
			->willReturn( $newTag );

		$result = $this->_resolver->resolveFromString( 'PHP, Rust' );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'PHP', $result[0]->getName() );
		$this->assertEquals( 'Rust', $result[1]->getName() );
	}
}
