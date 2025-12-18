<?php

namespace Tests\Cms\Services\Page;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Page\Deleter;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;

class DeleterTest extends TestCase
{
	public function testDeletePageSuccessfully(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$deleter = new Deleter( $repository );

		$page = new Page();
		$page->setId( 1 );

		$repository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( true );

		$result = $deleter->delete( $page );

		$this->assertTrue( $result );
	}

	public function testDeletePageWithoutIdReturnsFalse(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$deleter = new Deleter( $repository );

		$page = new Page();
		// Page has no ID set

		$repository
			->expects( $this->never() )
			->method( 'delete' );

		$result = $deleter->delete( $page );

		$this->assertFalse( $result );
	}

	public function testDeletePageWhenRepositoryFails(): void
	{
		$repository = $this->createMock( IPageRepository::class );
		$deleter = new Deleter( $repository );

		$page = new Page();
		$page->setId( 1 );

		$repository
			->expects( $this->once() )
			->method( 'delete' )
			->with( 1 )
			->willReturn( false );

		$result = $deleter->delete( $page );

		$this->assertFalse( $result );
	}
}
