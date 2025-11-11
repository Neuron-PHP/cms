<?php

namespace Tests\Cms\Services\Tag;

use Neuron\Cms\Models\Tag;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Tag\Creator;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
	private Creator $_creator;
	private ITagRepository $_mockRepository;

	protected function setUp(): void
	{
		$this->_mockRepository = $this->createMock( ITagRepository::class );
		$this->_creator = new Creator( $this->_mockRepository );
	}

	public function testCreatesTagWithGivenNameAndSlug(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( 'PHP' );
		$expectedTag->setSlug( 'custom-slug' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getName() === 'PHP' && $tag->getSlug() === 'custom-slug';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( 'PHP', 'custom-slug' );

		$this->assertEquals( 1, $result->getId() );
		$this->assertEquals( 'PHP', $result->getName() );
		$this->assertEquals( 'custom-slug', $result->getSlug() );
	}

	public function testGeneratesSlugWhenNotProvided(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( 'Test Tag Name' );
		$expectedTag->setSlug( 'test-tag-name' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getName() === 'Test Tag Name'
					&& $tag->getSlug() === 'test-tag-name';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( 'Test Tag Name' );

		$this->assertEquals( 'test-tag-name', $result->getSlug() );
	}

	public function testSlugGenerationHandlesSpecialCharacters(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( 'C++ Programming!' );
		$expectedTag->setSlug( 'c-programming' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getSlug() === 'c-programming';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( 'C++ Programming!' );

		$this->assertEquals( 'c-programming', $result->getSlug() );
	}

	public function testSlugGenerationHandlesMultipleSpaces(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( 'Multiple   Spaces   Here' );
		$expectedTag->setSlug( 'multiple-spaces-here' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getSlug() === 'multiple-spaces-here';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( 'Multiple   Spaces   Here' );

		$this->assertEquals( 'multiple-spaces-here', $result->getSlug() );
	}

	public function testSlugGenerationTrimsLeadingAndTrailingDashes(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( '---Test---' );
		$expectedTag->setSlug( 'test' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getSlug() === 'test';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( '---Test---' );

		$this->assertEquals( 'test', $result->getSlug() );
	}

	public function testSlugGenerationConvertsToLowercase(): void
	{
		$expectedTag = new Tag();
		$expectedTag->setId( 1 );
		$expectedTag->setName( 'UPPERCASE TAG' );
		$expectedTag->setSlug( 'uppercase-tag' );

		$this->_mockRepository
			->expects( $this->once() )
			->method( 'create' )
			->with( $this->callback( function( Tag $tag ) {
				return $tag->getSlug() === 'uppercase-tag';
			} ) )
			->willReturn( $expectedTag );

		$result = $this->_creator->create( 'UPPERCASE TAG' );

		$this->assertEquals( 'uppercase-tag', $result->getSlug() );
	}
}
