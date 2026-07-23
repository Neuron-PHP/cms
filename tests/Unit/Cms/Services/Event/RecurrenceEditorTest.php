<?php

namespace Tests\Unit\Cms\Services\Event;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Services\Event\RecurrenceEditor;
use Neuron\Dto\Factory;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class RecurrenceEditorTest extends TestCase
{
	private function dto( array $values ): Dto
	{
		$factory = new Factory( __DIR__ . '/../../../../../src/Cms/Dtos/events/update-event-request.yaml' );
		$dto = $factory->create();

		foreach( $values as $key => $value )
		{
			$dto->$key = $value;
		}

		return $dto;
	}

	private function master(): Event
	{
		$master = new Event();
		$master->setId( 10 );
		$master->setTitle( 'Weekly' );
		$master->setSlug( 'weekly' );
		$master->setStartDate( new DateTimeImmutable( '2026-01-05 09:00:00' ) );
		$master->setEndDate( new DateTimeImmutable( '2026-01-05 10:00:00' ) );
		$master->setStatus( 'published' );
		$master->setRrule( 'FREQ=WEEKLY' );
		$master->setCreatedBy( 1 );

		return $master;
	}

	public function testEditSingleCreatesOverrideRow(): void
	{
		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->method( 'findOverride' )->willReturn( null );
		$repo->method( 'slugExists' )->willReturn( false );
		$repo->expects( $this->once() )
			->method( 'create' )
			->willReturnArgument( 0 );
		$repo->expects( $this->never() )->method( 'update' );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$override = $editor->editSingle( $this->master(), $this->dto( [
			'id'              => 10,
			'title'           => 'Moved Standup',
			'start_date'      => '2026-01-12 14:00:00',
			'end_date'        => '2026-01-12 15:00:00',
			'status'          => 'published',
			'occurrence_date' => '2026-01-12 09:00:00'
		] ) );

		$this->assertTrue( $override->isRecurrenceOverride() );
		$this->assertSame( 10, $override->getRecurrenceParentId() );
		$this->assertSame( '2026-01-12 09:00:00', $override->getRecurrenceId()->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-01-12 14:00:00', $override->getStartDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertNull( $override->getRrule() );
		$this->assertSame( 'Moved Standup', $override->getTitle() );
	}

	public function testEditSingleUpdatesExistingOverride(): void
	{
		$existing = new Event();
		$existing->setId( 55 );
		$existing->setSlug( 'weekly-existing' );
		$existing->setRecurrenceParentId( 10 );
		$existing->setRecurrenceId( new DateTimeImmutable( '2026-01-12 09:00:00' ) );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->method( 'findOverride' )->willReturn( $existing );
		$repo->expects( $this->never() )->method( 'create' );
		$repo->expects( $this->once() )
			->method( 'update' )
			->willReturnArgument( 0 );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$override = $editor->editSingle( $this->master(), $this->dto( [
			'id'              => 10,
			'title'           => 'Updated',
			'start_date'      => '2026-01-12 16:00:00',
			'status'          => 'published',
			'occurrence_date' => '2026-01-12 09:00:00'
		] ) );

		$this->assertSame( 55, $override->getId() );
		$this->assertSame( 'Updated', $override->getTitle() );
		$this->assertSame( 'weekly-existing', $override->getSlug() );
	}

	public function testEditSingleRequiresOccurrenceDate(): void
	{
		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$this->expectException( \RuntimeException::class );

		$editor->editSingle( $this->master(), $this->dto( [
			'id'         => 10,
			'title'      => 'No occurrence',
			'start_date' => '2026-01-12 14:00:00',
			'status'     => 'published'
		] ) );
	}

	public function testSplitFromOccurrenceBoundsMasterAndCreatesRemainder(): void
	{
		$master = $this->master();

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->method( 'slugExists' )->willReturn( false );

		$created = null;
		$repo->expects( $this->once() )
			->method( 'update' )
			->willReturnArgument( 0 );
		$repo->expects( $this->once() )
			->method( 'create' )
			->willReturnCallback( function( Event $event ) use ( &$created )
			{
				$created = $event;
				return $event;
			} );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$remainder = $editor->splitFromOccurrence( $master, $this->dto( [
			'id'              => 10,
			'title'           => 'New Series',
			'start_date'      => '2026-01-19 11:00:00',
			'end_date'        => '2026-01-19 12:00:00',
			'status'          => 'published',
			'occurrence_date' => '2026-01-19 09:00:00'
		] ) );

		// Master is bounded to end before the split (last prior occurrence Jan 12).
		$this->assertStringContainsString( 'UNTIL=20260112T090000Z', (string)$master->getRrule() );

		// Remainder is a new master carrying the edits.
		$this->assertSame( $created, $remainder );
		$this->assertSame( 'New Series', $remainder->getTitle() );
		$this->assertSame( '2026-01-19 11:00:00', $remainder->getStartDate()->format( 'Y-m-d H:i:s' ) );
		$this->assertTrue( $remainder->isRecurring() );
		$this->assertStringContainsString( 'FREQ=WEEKLY', (string)$remainder->getRrule() );
		$this->assertNull( $remainder->getRecurrenceParentId() );
	}

	public function testCancelOccurrenceAddsException(): void
	{
		$occurrence = new DateTimeImmutable( '2026-01-12 09:00:00' );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->expects( $this->once() )
			->method( 'addException' )
			->with( 10, $this->callback(
				fn( DateTimeImmutable $date ) => $date->format( 'Y-m-d H:i:s' ) === '2026-01-12 09:00:00'
			) );
		$repo->method( 'findOverride' )->willReturn( null );
		$repo->expects( $this->never() )->method( 'delete' );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );
		$editor->cancelOccurrence( $this->master(), $occurrence );
	}

	public function testCancelOccurrenceDeletesOverrideWhenPresent(): void
	{
		$occurrence = new DateTimeImmutable( '2026-01-12 09:00:00' );

		$override = new Event();
		$override->setId( 77 );
		$override->setRecurrenceParentId( 10 );
		$override->setRecurrenceId( $occurrence );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->expects( $this->once() )->method( 'addException' );
		$repo->method( 'findOverride' )->willReturn( $override );
		$repo->expects( $this->once() )->method( 'delete' )->with( $override );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );
		$editor->cancelOccurrence( $this->master(), $occurrence );
	}

	public function testCancelOccurrenceRejectsNonSeriesDate(): void
	{
		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->expects( $this->never() )->method( 'addException' );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'not an occurrence' );

		$editor->cancelOccurrence(
			$this->master(),
			new DateTimeImmutable( '2026-01-13 09:00:00' )
		);
	}

	public function testCancelOccurrenceRejectsNonRecurringEvent(): void
	{
		$event = $this->master();
		$event->setRrule( null );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );

		$this->expectException( \RuntimeException::class );

		$editor->cancelOccurrence( $event, new DateTimeImmutable( '2026-01-12 09:00:00' ) );
	}

	public function testListOccurrencesReturnsSeriesDatesWithCancelledFlags(): void
	{
		// Anchor to an upcoming Monday so the "from today" window is deterministic.
		$start = new DateTimeImmutable( 'monday this week' );
		if( $start < new DateTimeImmutable( 'today' ) )
		{
			$start = $start->modify( '+1 week' );
		}
		$start = $start->setTime( 9, 0, 0 );
		$cancelledDate = $start->modify( '+1 week' );

		$master = $this->master();
		$master->setStartDate( $start );
		$master->setRrule( 'FREQ=WEEKLY;BYDAY=MO' );
		$master->setRecurrenceUntil( $start->modify( '+4 weeks' ) );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->method( 'getExceptions' )->with( 10 )->willReturn( [ $cancelledDate ] );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );
		$rows = $editor->listOccurrences( $master, 10 );

		$this->assertNotEmpty( $rows );
		$this->assertSame( $start->format( 'Y-m-d H:i:s' ), $rows[0]['value'] );
		$this->assertFalse( $rows[0]['cancelled'] );

		$cancelled = array_values( array_filter(
			$rows,
			fn( array $row ) => $row['value'] === $cancelledDate->format( 'Y-m-d H:i:s' )
		) );
		$this->assertCount( 1, $cancelled );
		$this->assertTrue( $cancelled[0]['cancelled'] );

		foreach( $rows as $row )
		{
			$this->assertSame( 'Monday', $row['occurrence']->format( 'l' ) );
		}
	}

	public function testRestoreOccurrenceRemovesException(): void
	{
		$occurrence = new DateTimeImmutable( '2026-01-12 09:00:00' );

		$repo = $this->createMock( IEventRepository::class );
		$categoryRepo = $this->createMock( IEventCategoryRepository::class );

		$repo->expects( $this->once() )
			->method( 'removeException' )
			->with( 10, $this->callback(
				fn( DateTimeImmutable $date ) => $date->format( 'Y-m-d H:i:s' ) === '2026-01-12 09:00:00'
			) );

		$editor = new RecurrenceEditor( $repo, $categoryRepo );
		$editor->restoreOccurrence( $this->master(), $occurrence );
	}
}
