<?php

namespace Tests\Unit\Cms\Services\Content;

use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use PHPUnit\Framework\TestCase;

class ShortcodeParserTest extends TestCase
{
	private ShortcodeParser $parser;

	protected function setUp(): void
	{
		parent::setUp();

		$this->parser = new ShortcodeParser();
	}

	public function testParseContentWithoutShortcodes(): void
	{
		$content = 'This is plain text without shortcodes.';

		$result = $this->parser->parse( $content );

		$this->assertSame( $content, $result );
	}

	public function testParseSimpleShortcode(): void
	{
		$this->parser->register( 'test', fn() => 'Rendered Test' );

		$content = 'Before [test] after';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'Before Rendered Test after', $result );
	}

	public function testParseShortcodeWithAttributes(): void
	{
		$this->parser->register( 'greeting', function( $attrs ) {
			return "Hello {$attrs['name']}!";
		});

		$content = 'Say [greeting name="World"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'Say Hello World!', $result );
	}

	public function testParseShortcodeWithMultipleAttributes(): void
	{
		$this->parser->register( 'link', function( $attrs ) {
			return "<a href='{$attrs['url']}'>{$attrs['text']}</a>";
		});

		$content = '[link url="https://example.com" text="Click here"]';
		$result = $this->parser->parse( $content );

		$this->assertStringContainsString( 'https://example.com', $result );
		$this->assertStringContainsString( 'Click here', $result );
	}

	public function testParseShortcodeWithHyphenatedName(): void
	{
		$this->parser->register( 'my-widget', fn() => 'My Widget' );

		$content = '[my-widget]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'My Widget', $result );
	}

	public function testParseShortcodeWithHyphenatedAttributes(): void
	{
		$this->parser->register( 'data', function( $attrs ) {
			return $attrs['data-id'] ?? 'no-id';
		});

		$content = '[data data-id="123"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( '123', $result );
	}

	public function testParseMultipleShortcodes(): void
	{
		$this->parser->register( 'foo', fn() => 'FOO' );
		$this->parser->register( 'bar', fn() => 'BAR' );

		$content = '[foo] and [bar]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'FOO and BAR', $result );
	}

	public function testParseAttributeWithBooleanTrue(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return $attrs['enabled'] === true ? 'enabled' : 'disabled';
		});

		$content = '[test enabled="true"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'enabled', $result );
	}

	public function testParseAttributeWithBooleanFalse(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return $attrs['enabled'] === false ? 'disabled' : 'enabled';
		});

		$content = '[test enabled="false"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'disabled', $result );
	}

	public function testParseAttributeWithInteger(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return 'Count: ' . ($attrs['count'] * 2);
		});

		$content = '[test count="5"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'Count: 10', $result );
	}

	public function testParseAttributeWithFloat(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return 'Price: $' . number_format( $attrs['price'], 2 );
		});

		$content = '[test price="19.99"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'Price: $19.99', $result );
	}

	public function testParseUnknownShortcodeReturnsComment(): void
	{
		$content = '[unknown-shortcode]';
		$result = $this->parser->parse( $content );

		$this->assertStringContainsString( '<!-- Unknown shortcode: [unknown-shortcode] -->', $result );
	}

	public function testRegisterShortcodeHandler(): void
	{
		$this->parser->register( 'custom', fn() => 'Custom Handler' );

		$this->assertTrue( $this->parser->hasShortcode( 'custom' ) );
	}

	public function testUnregisterShortcodeHandler(): void
	{
		$this->parser->register( 'custom', fn() => 'Custom' );
		$this->assertTrue( $this->parser->hasShortcode( 'custom' ) );

		$this->parser->unregister( 'custom' );
		$this->assertFalse( $this->parser->hasShortcode( 'custom' ) );
	}

	public function testHasShortcodeReturnsFalseForUnregistered(): void
	{
		$this->assertFalse( $this->parser->hasShortcode( 'nonexistent' ) );
	}

	public function testHasShortcodeReturnsTrueForBuiltIn(): void
	{
		$widgetRenderer = $this->createMock( WidgetRenderer::class );
		$parser = new ShortcodeParser( $widgetRenderer );

		$this->assertTrue( $parser->hasShortcode( 'latest-posts' ) );
	}

	public function testParseBuiltInShortcodeWithWidgetRenderer(): void
	{
		$widgetRenderer = $this->createMock( WidgetRenderer::class );
		$widgetRenderer->method( 'render' )->willReturn( '<div>Latest Posts Widget</div>' );

		$parser = new ShortcodeParser( $widgetRenderer );

		$content = '[latest-posts]';
		$result = $parser->parse( $content );

		$this->assertStringContainsString( 'Latest Posts Widget', $result );
	}

	public function testParseBuiltInShortcodeWithoutWidgetRenderer(): void
	{
		$content = '[latest-posts]';
		$result = $this->parser->parse( $content );

		$this->assertStringContainsString( '<!-- Unknown shortcode: [latest-posts] -->', $result );
	}

	public function testCustomHandlerExceptionReturnsErrorComment(): void
	{
		$this->parser->register( 'error', function() {
			throw new \RuntimeException( 'Test error' );
		});

		$content = '[error]';
		$result = $this->parser->parse( $content );

		$this->assertStringContainsString( '<!-- Error in custom shortcode [error] -->', $result );
	}

	public function testBuiltInHandlerExceptionReturnsErrorComment(): void
	{
		$widgetRenderer = $this->createMock( WidgetRenderer::class );
		$widgetRenderer->method( 'render' )->willThrowException( new \RuntimeException( 'Widget error' ) );

		$parser = new ShortcodeParser( $widgetRenderer );

		$content = '[latest-posts]';
		$result = $parser->parse( $content );

		$this->assertStringContainsString( '<!-- Error rendering shortcode [latest-posts] -->', $result );
	}

	public function testParseAttributesWithSingleQuotes(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return $attrs['value'];
		});

		$content = "[test value='single-quoted']";
		$result = $this->parser->parse( $content );

		$this->assertSame( 'single-quoted', $result );
	}

	public function testParseAttributesWithDoubleQuotes(): void
	{
		$this->parser->register( 'test', function( $attrs ) {
			return $attrs['value'];
		});

		$content = '[test value="double-quoted"]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'double-quoted', $result );
	}

	public function testParseShortcodeWithNoAttributes(): void
	{
		$this->parser->register( 'simple', function( $attrs ) {
			return empty( $attrs ) ? 'no-attrs' : 'has-attrs';
		});

		$content = '[simple]';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'no-attrs', $result );
	}

	public function testParsePreservesNonShortcodeContent(): void
	{
		$this->parser->register( 'widget', fn() => 'WIDGET' );

		$content = 'Before [widget] middle text [widget] after';
		$result = $this->parser->parse( $content );

		$this->assertSame( 'Before WIDGET middle text WIDGET after', $result );
	}

	public function testParseHandlesEmptyString(): void
	{
		$result = $this->parser->parse( '' );

		$this->assertSame( '', $result );
	}

	public function testParseShortcodeCanAccessAllAttributes(): void
	{
		$this->parser->register( 'attrs', function( $attrs ) {
			return implode( ',', array_keys( $attrs ) );
		});

		$content = '[attrs foo="1" bar="2" baz="3"]';
		$result = $this->parser->parse( $content );

		$this->assertStringContainsString( 'foo', $result );
		$this->assertStringContainsString( 'bar', $result );
		$this->assertStringContainsString( 'baz', $result );
	}
}
