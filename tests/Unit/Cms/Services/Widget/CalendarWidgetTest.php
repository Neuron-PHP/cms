<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Services\Widget\CalendarWidget;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use PHPUnit\Framework\TestCase;

class CalendarWidgetTest extends TestCase
{
	private $eventRepository;
	private $categoryRepository;
	private $widget;

	protected function setUp(): void
	{
		$this->eventRepository = $this->createMock( DatabaseEventRepository::class );
		$this->categoryRepository = $this->createMock( DatabaseEventCategoryRepository::class );

		$this->widget = new CalendarWidget(
			$this->eventRepository,
			$this->categoryRepository
		);
	}

	public function test_upcoming_defaults_to_true_when_not_specified(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( [] );
	}

	public function test_upcoming_true_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => true] );
	}

	public function test_upcoming_false_string_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => 'false'] );
	}

	public function test_upcoming_false_boolean_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => false] );
	}

	public function test_upcoming_zero_string_calls_get_past(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getPast' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => '0'] );
	}

	public function test_upcoming_one_string_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => '1'] );
	}

	public function test_upcoming_true_string_calls_get_upcoming(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( ['upcoming' => 'true'] );
	}

	public function test_limit_is_respected(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 10, 'published' )
			->willReturn( [] );

		$this->widget->render( ['limit' => 10] );
	}

	public function test_limit_defaults_to_5(): void
	{
		$this->eventRepository->expects( $this->once() )
			->method( 'getUpcoming' )
			->with( 5, 'published' )
			->willReturn( [] );

		$this->widget->render( [] );
	}
}
