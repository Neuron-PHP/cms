<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Services\Event\Deleter;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use PHPUnit\Framework\TestCase;

class DeleterTest extends TestCase
{
	private Deleter $deleter;
	private $eventRepository;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( IEventRepository::class );

		$this->deleter = new Deleter( $this->eventRepository );
	}

	public function test_delete_returns_true_on_success(): void
	{
		$event = new Event();
		$event->setId( 1 );
		$event->setTitle( 'Event to Delete' );

		$this->eventRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $event )
			->willReturn( true );

		$result = $this->deleter->delete( $event );

		$this->assertTrue( $result );
	}

	public function test_delete_returns_false_on_failure(): void
	{
		$event = new Event();
		$event->setId( 1 );

		$this->eventRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $event )
			->willReturn( false );

		$result = $this->deleter->delete( $event );

		$this->assertFalse( $result );
	}

	public function test_delete_calls_repository_delete(): void
	{
		$event = new Event();
		$event->setId( 5 );

		$this->eventRepository->expects( $this->once() )
			->method( 'delete' )
			->with( $this->identicalTo( $event ) )
			->willReturn( true );

		$this->deleter->delete( $event );
	}
}
