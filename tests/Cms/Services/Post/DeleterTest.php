<?php

namespace Tests\Cms\Services\Post;

use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Services\Post\Deleter;
use PHPUnit\Framework\TestCase;

class DeleterTest extends TestCase
{
	private Deleter $_deleter;
	private IPostRepository $_mockPostRepository;

	protected function setUp(): void
	{
		$this->_mockPostRepository = $this->createMock( IPostRepository::class );
		$this->_deleter = new Deleter( $this->_mockPostRepository );
	}

	public function testDeletesPostWithId(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( true );

		$result = $this->_deleter->delete( $post );

		$this->assertTrue( $result );
	}

	public function testReturnsFalseWhenDeletingPostWithoutId(): void
	{
		$post = new Post();
		// Post has no ID set

		$this->_mockPostRepository
			->expects( $this->never() )
			->method( 'delete' );

		$result = $this->_deleter->delete( $post );

		$this->assertFalse( $result );
	}

	public function testDeletesByIdDirectly(): void
	{
		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 42 )
			->willReturn( true );

		$result = $this->_deleter->deleteById( 42 );

		$this->assertTrue( $result );
	}

	public function testDeleteByIdReturnsFalseWhenRepositoryFails(): void
	{
		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 999 )
			->willReturn( false );

		$result = $this->_deleter->deleteById( 999 );

		$this->assertFalse( $result );
	}

	public function testDeleteReturnsFalseWhenRepositoryFails(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$this->_mockPostRepository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( false );

		$result = $this->_deleter->delete( $post );

		$this->assertFalse( $result );
	}
}
