<?php

namespace Tests\Cms\Services\Widget;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Widget\WidgetRegistry;
use Neuron\Cms\Services\Widget\IWidget;
use Neuron\Cms\Services\Content\ShortcodeParser;

class WidgetRegistryTest extends TestCase
{
	public function testRegisterWidgetAddsToRegistry(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$widget = $this->createMock( IWidget::class );
		$widget->method( 'getName' )->willReturn( 'test-widget' );

		$parser
			->expects( $this->once() )
			->method( 'register' )
			->with( 'test-widget', $this->isType( 'callable' ) );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget );

		$this->assertTrue( $registry->has( 'test-widget' ) );
		$this->assertSame( $widget, $registry->get( 'test-widget' ) );
	}

	public function testRegisterMultipleWidgets(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );

		$widget1 = $this->createMock( IWidget::class );
		$widget1->method( 'getName' )->willReturn( 'widget-one' );

		$widget2 = $this->createMock( IWidget::class );
		$widget2->method( 'getName' )->willReturn( 'widget-two' );

		$parser
			->expects( $this->exactly( 2 ) )
			->method( 'register' );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget1 );
		$registry->register( $widget2 );

		$this->assertTrue( $registry->has( 'widget-one' ) );
		$this->assertTrue( $registry->has( 'widget-two' ) );
		$this->assertCount( 2, $registry->getAll() );
	}

	public function testUnregisterRemovesWidget(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$widget = $this->createMock( IWidget::class );
		$widget->method( 'getName' )->willReturn( 'test-widget' );

		$parser
			->expects( $this->once() )
			->method( 'register' );

		$parser
			->expects( $this->once() )
			->method( 'unregister' )
			->with( 'test-widget' );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget );

		$this->assertTrue( $registry->has( 'test-widget' ) );

		$registry->unregister( 'test-widget' );

		$this->assertFalse( $registry->has( 'test-widget' ) );
		$this->assertNull( $registry->get( 'test-widget' ) );
	}

	public function testUnregisterNonExistentWidgetDoesNothing(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );

		$parser
			->expects( $this->never() )
			->method( 'unregister' );

		$registry = new WidgetRegistry( $parser );
		$registry->unregister( 'nonexistent' );

		$this->assertFalse( $registry->has( 'nonexistent' ) );
	}

	public function testGetAllReturnsAllWidgets(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );

		$widget1 = $this->createMock( IWidget::class );
		$widget1->method( 'getName' )->willReturn( 'widget-one' );

		$widget2 = $this->createMock( IWidget::class );
		$widget2->method( 'getName' )->willReturn( 'widget-two' );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget1 );
		$registry->register( $widget2 );

		$all = $registry->getAll();

		$this->assertIsArray( $all );
		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'widget-one', $all );
		$this->assertArrayHasKey( 'widget-two', $all );
		$this->assertSame( $widget1, $all['widget-one'] );
		$this->assertSame( $widget2, $all['widget-two'] );
	}

	public function testGetAllReturnsEmptyArrayWhenNoWidgets(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$registry = new WidgetRegistry( $parser );

		$all = $registry->getAll();

		$this->assertIsArray( $all );
		$this->assertEmpty( $all );
	}

	public function testGetReturnsWidgetWhenExists(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$widget = $this->createMock( IWidget::class );
		$widget->method( 'getName' )->willReturn( 'test-widget' );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget );

		$retrieved = $registry->get( 'test-widget' );

		$this->assertSame( $widget, $retrieved );
	}

	public function testGetReturnsNullWhenWidgetDoesNotExist(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$registry = new WidgetRegistry( $parser );

		$result = $registry->get( 'nonexistent' );

		$this->assertNull( $result );
	}

	public function testHasReturnsTrueWhenWidgetExists(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$widget = $this->createMock( IWidget::class );
		$widget->method( 'getName' )->willReturn( 'test-widget' );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget );

		$this->assertTrue( $registry->has( 'test-widget' ) );
	}

	public function testHasReturnsFalseWhenWidgetDoesNotExist(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$registry = new WidgetRegistry( $parser );

		$this->assertFalse( $registry->has( 'nonexistent' ) );
	}

	public function testRegisterCallsWidgetRenderWhenShortcodeCalled(): void
	{
		$parser = $this->createMock( ShortcodeParser::class );
		$widget = $this->createMock( IWidget::class );
		$widget->method( 'getName' )->willReturn( 'test-widget' );
		$widget
			->expects( $this->once() )
			->method( 'render' )
			->with( ['attr' => 'value'] )
			->willReturn( '<div>Test Output</div>' );

		$callback = null;
		$parser
			->method( 'register' )
			->willReturnCallback( function( $name, $cb ) use ( &$callback ) {
				$callback = $cb;
			} );

		$registry = new WidgetRegistry( $parser );
		$registry->register( $widget );

		// Simulate shortcode parser calling the callback
		$result = $callback( ['attr' => 'value'] );

		$this->assertEquals( '<div>Test Output</div>', $result );
	}
}
