<?php

namespace Tests\Unit\Cms\Services\Content;

use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use PHPUnit\Framework\TestCase;

class EditorJsRendererTest extends TestCase
{
	private EditorJsRenderer $renderer;

	protected function setUp(): void
	{
		parent::setUp();

		$this->renderer = new EditorJsRenderer();
	}

	public function testRenderEmptyData(): void
	{
		$result = $this->renderer->render( [] );

		$this->assertSame( '', $result );
	}

	public function testRenderEmptyBlocks(): void
	{
		$result = $this->renderer->render( [ 'blocks' => [] ] );

		$this->assertSame( '', $result );
	}

	public function testRenderParagraphBlock(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'paragraph', 'data' => [ 'text' => 'Hello World' ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<p', $result );
		$this->assertStringContainsString( 'Hello World', $result );
		$this->assertStringContainsString( '</p>', $result );
	}

	public function testRenderHeaderBlock(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'header', 'data' => [ 'text' => 'Title', 'level' => 1 ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<h1', $result );
		$this->assertStringContainsString( 'Title', $result );
		$this->assertStringContainsString( '</h1>', $result );
	}

	public function testRenderHeaderWithDifferentLevels(): void
	{
		for( $level = 1; $level <= 6; $level++ )
		{
			$data = [
				'blocks' => [
					[ 'type' => 'header', 'data' => [ 'text' => "Level {$level}", 'level' => $level ] ]
				]
			];

			$result = $this->renderer->render( $data );

			$this->assertStringContainsString( "<h{$level}", $result );
			$this->assertStringContainsString( "Level {$level}", $result );
			$this->assertStringContainsString( "</h{$level}>", $result );
		}
	}

	public function testRenderHeaderClampsInvalidLevel(): void
	{
		// Test level > 6 (should clamp to 6)
		$data = [
			'blocks' => [
				[ 'type' => 'header', 'data' => [ 'text' => 'Test', 'level' => 10 ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<h6', $result );

		// Test level < 1 (should clamp to 1)
		$data = [
			'blocks' => [
				[ 'type' => 'header', 'data' => [ 'text' => 'Test', 'level' => 0 ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<h1', $result );
	}

	public function testRenderUnorderedList(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [ 'Item 1', 'Item 2', 'Item 3' ]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<ul', $result );
		$this->assertStringContainsString( '<li>Item 1</li>', $result );
		$this->assertStringContainsString( '<li>Item 2</li>', $result );
		$this->assertStringContainsString( '<li>Item 3</li>', $result );
		$this->assertStringContainsString( '</ul>', $result );
	}

	public function testRenderOrderedList(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'ordered',
						'items' => [ 'First', 'Second', 'Third' ]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<ol', $result );
		$this->assertStringContainsString( '<li>First</li>', $result );
		$this->assertStringContainsString( '<li>Second</li>', $result );
		$this->assertStringContainsString( '<li>Third</li>', $result );
		$this->assertStringContainsString( '</ol>', $result );
	}

	public function testRenderImageBlock(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'image',
					'data' => [
						'file' => [ 'url' => 'https://example.com/image.jpg' ],
						'caption' => 'Test Image'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<figure', $result );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'https://example.com/image.jpg', $result );
		$this->assertStringContainsString( 'Test Image', $result );
		$this->assertStringContainsString( '<figcaption', $result );
		$this->assertStringContainsString( '</figure>', $result );
	}

	public function testRenderImageWithoutCaption(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'image',
					'data' => [
						'file' => [ 'url' => 'https://example.com/image.jpg' ]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( '<figcaption', $result );
	}

	public function testRenderQuoteBlock(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'quote',
					'data' => [
						'text' => 'This is a quote',
						'caption' => 'Author Name'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<blockquote', $result );
		$this->assertStringContainsString( 'This is a quote', $result );
		$this->assertStringContainsString( '<footer', $result );
		$this->assertStringContainsString( 'Author Name', $result );
		$this->assertStringContainsString( '</blockquote>', $result );
	}

	public function testRenderQuoteWithAlignment(): void
	{
		$alignments = [
			'left' => '',
			'center' => 'text-center',
			'right' => 'text-end'
		];

		foreach( $alignments as $alignment => $expectedClass )
		{
			$data = [
				'blocks' => [
					[
						'type' => 'quote',
						'data' => [
							'text' => 'Test quote',
							'alignment' => $alignment
						]
					]
				]
			];

			$result = $this->renderer->render( $data );

			if( $expectedClass )
			{
				$this->assertStringContainsString( $expectedClass, $result );
			}
		}
	}

	public function testRenderCodeBlock(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'code',
					'data' => [
						'code' => 'function test() { return true; }'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<pre', $result );
		$this->assertStringContainsString( '<code>', $result );
		$this->assertStringContainsString( 'function test()', $result );
		$this->assertStringContainsString( '</code>', $result );
		$this->assertStringContainsString( '</pre>', $result );
	}

	public function testRenderDelimiterBlock(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'delimiter', 'data' => [] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<hr', $result );
	}

	public function testRenderRawHtmlBlock(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'raw',
					'data' => [
						'html' => '<div>Test Content</div>'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		// Raw HTML should be sanitized (div tags stripped)
		$this->assertStringContainsString( 'Test Content', $result );
	}

	public function testRenderUnknownBlockType(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'unknown-type', 'data' => [] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<!-- Unknown block type: unknown-type -->', $result );
	}

	public function testRenderMultipleBlocks(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'header', 'data' => [ 'text' => 'Title', 'level' => 1 ] ],
				[ 'type' => 'paragraph', 'data' => [ 'text' => 'First paragraph' ] ],
				[ 'type' => 'paragraph', 'data' => [ 'text' => 'Second paragraph' ] ],
				[ 'type' => 'delimiter', 'data' => [] ],
				[ 'type' => 'quote', 'data' => [ 'text' => 'A quote' ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<h1', $result );
		$this->assertStringContainsString( 'First paragraph', $result );
		$this->assertStringContainsString( 'Second paragraph', $result );
		$this->assertStringContainsString( '<hr', $result );
		$this->assertStringContainsString( '<blockquote', $result );
	}

	public function testSanitizesHtmlInContent(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'paragraph', 'data' => [ 'text' => '<script>alert("xss")</script>Safe text' ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( 'Safe text', $result );
	}

	public function testAllowsSafeHtmlTags(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'paragraph', 'data' => [ 'text' => 'Text with <strong>bold</strong> and <em>italic</em>' ] ]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<strong>bold</strong>', $result );
		$this->assertStringContainsString( '<em>italic</em>', $result );
	}

	public function testRendererWithShortcodeParser(): void
	{
		$shortcodeParser = $this->createMock( ShortcodeParser::class );
		$shortcodeParser->method( 'parse' )->willReturn( 'Parsed shortcode' );

		$renderer = new EditorJsRenderer( $shortcodeParser );

		$data = [
			'blocks' => [
				[ 'type' => 'paragraph', 'data' => [ 'text' => '[shortcode]' ] ]
			]
		];

		$result = $renderer->render( $data );

		$this->assertStringContainsString( 'Parsed shortcode', $result );
	}

	public function testBlockWithMissingData(): void
	{
		$data = [
			'blocks' => [
				[ 'type' => 'paragraph' ], // Missing 'data' key
			]
		];

		$result = $this->renderer->render( $data );

		// Should not throw exception, should render empty paragraph
		$this->assertStringContainsString( '<p', $result );
	}

	public function testRenderNestedUnorderedList(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [
							'Simple item 1',
							[
								'content' => 'Item with nested list',
								'items' => [
									'Nested item 1',
									'Nested item 2'
								]
							],
							'Simple item 2'
						]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<ul', $result );
		$this->assertStringContainsString( 'Simple item 1', $result );
		$this->assertStringContainsString( 'Item with nested list', $result );
		$this->assertStringContainsString( 'Nested item 1', $result );
		$this->assertStringContainsString( 'Nested item 2', $result );
		$this->assertStringContainsString( 'Simple item 2', $result );

		// Check for nested <ul> structure
		$this->assertMatchesRegularExpression( '/<ul[^>]*>.*<ul[^>]*>.*<\/ul>.*<\/ul>/s', $result );
	}

	public function testRenderNestedOrderedList(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'ordered',
						'items' => [
							'First item',
							[
								'content' => 'Second item with sub-items',
								'items' => [
									'Sub-item A',
									'Sub-item B'
								]
							]
						]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<ol', $result );
		$this->assertStringContainsString( 'First item', $result );
		$this->assertStringContainsString( 'Second item with sub-items', $result );
		$this->assertStringContainsString( 'Sub-item A', $result );
		$this->assertStringContainsString( 'Sub-item B', $result );

		// Check for nested <ol> structure
		$this->assertMatchesRegularExpression( '/<ol[^>]*>.*<ol[^>]*>.*<\/ol>.*<\/ol>/s', $result );
	}

	public function testRenderMultiLevelNestedList(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [
							[
								'content' => 'Level 1 item',
								'items' => [
									[
										'content' => 'Level 2 item',
										'items' => [
											'Level 3 item'
										]
									]
								]
							]
						]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( 'Level 1 item', $result );
		$this->assertStringContainsString( 'Level 2 item', $result );
		$this->assertStringContainsString( 'Level 3 item', $result );

		// Should have multiple nested <ul> tags (3 levels)
		$ulCount = substr_count( $result, '<ul' );
		$this->assertGreaterThanOrEqual( 3, $ulCount );
	}

	public function testRenderNestedListWithEmptyItems(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [
							[
								'content' => 'Item with empty nested list',
								'items' => []
							]
						]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( 'Item with empty nested list', $result );

		// Should only have one <ul> since nested items array is empty
		$ulCount = substr_count( $result, '<ul' );
		$this->assertEquals( 1, $ulCount );
	}

	public function testRenderNestedListPreservesLegacyFormat(): void
	{
		// Old format: all items are simple strings
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [ 'Item 1', 'Item 2', 'Item 3' ]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<ul', $result );
		$this->assertStringContainsString( '<li>Item 1</li>', $result );
		$this->assertStringContainsString( '<li>Item 2</li>', $result );
		$this->assertStringContainsString( '<li>Item 3</li>', $result );

		// Should only have one <ul> (no nesting)
		$ulCount = substr_count( $result, '<ul' );
		$this->assertEquals( 1, $ulCount );
	}

	public function testRenderEmbedBlockYouTube(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'source' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
						'embed' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
						'width' => 580,
						'height' => 320,
						'caption' => 'Sample Video'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<figure', $result );
		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'youtube.com/embed', $result );
		$this->assertStringContainsString( 'Sample Video', $result );
		$this->assertStringContainsString( 'sandbox=', $result );
		$this->assertStringContainsString( '</figure>', $result );
	}

	public function testRenderEmbedBlockVimeo(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'vimeo',
						'source' => 'https://vimeo.com/123456789',
						'embed' => 'https://player.vimeo.com/video/123456789',
						'width' => 580,
						'height' => 320
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'player.vimeo.com', $result );
		$this->assertStringNotContainsString( '<figcaption', $result ); // No caption
	}

	public function testRenderEmbedBlockWithCaption(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'codepen',
						'embed' => 'https://codepen.io/embed/abc123',
						'caption' => 'Cool CodePen Demo'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<figcaption', $result );
		$this->assertStringContainsString( 'Cool CodePen Demo', $result );
	}

	public function testRenderEmbedBlockMissingUrl(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube'
						// Missing 'embed' URL
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<!-- Embed block missing URL -->', $result );
	}

	public function testRenderEmbedBlockUntrustedDomain(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'unknown',
						'embed' => 'https://evil.com/malicious.html'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<!-- Embed from untrusted domain', $result );
		$this->assertStringNotContainsString( '<iframe', $result );
	}

	public function testRenderEmbedBlockSecuritySandbox(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'embed' => 'https://www.youtube.com/embed/test123'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		// Verify sandbox attributes for security
		$this->assertStringContainsString( "sandbox='allow-scripts allow-same-origin allow-presentation allow-popups'", $result );
	}

	public function testRenderEmbedBlockResponsive(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'embed' => 'https://www.youtube.com/embed/test123'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		// Verify responsive wrapper
		$this->assertStringContainsString( "class='ratio ratio-16x9'", $result );
		$this->assertStringContainsString( "class='embed-responsive my-4'", $result );
	}

	public function testRenderEmbedBlockEscapesCaption(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'embed' => 'https://www.youtube.com/embed/test123',
						'caption' => '<script>alert("xss")</script>Safe Caption'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		// Should escape HTML in caption
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
		$this->assertStringContainsString( 'Safe Caption', $result );
	}

	public function testRenderEmbedWithTwitterService(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'twitter',
						'embed' => 'https://platform.twitter.com/embed/Tweet.html?id=123456'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'platform.twitter.com', $result );
	}

	public function testRenderEmbedWithInstagramService(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'instagram',
						'embed' => 'https://www.instagram.com/p/ABC123/embed'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'instagram.com', $result );
	}

	public function testRenderEmbedWithGitHubGistService(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'github',
						'embed' => 'https://gist.github.com/username/abc123'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'gist.github.com', $result );
	}

	public function testRenderParagraphWithShortcode(): void
	{
		$shortcodeParser = new ShortcodeParser();
		$shortcodeParser->register( 'test', fn() => 'SHORTCODE_OUTPUT' );

		$renderer = new EditorJsRenderer( $shortcodeParser );

		$data = [
			'blocks' => [
				[
					'type' => 'paragraph',
					'data' => [
						'text' => 'Content with [test] shortcode'
					]
				]
			]
		];

		$result = $renderer->render( $data );

		$this->assertStringContainsString( 'SHORTCODE_OUTPUT', $result );
		$this->assertStringContainsString( 'Content with', $result );
		$this->assertStringContainsString( 'shortcode', $result );
	}

	public function testRenderHeaderWithShortcode(): void
	{
		$shortcodeParser = new ShortcodeParser();
		$shortcodeParser->register( 'dynamic', fn() => 'DYNAMIC_CONTENT' );

		$renderer = new EditorJsRenderer( $shortcodeParser );

		$data = [
			'blocks' => [
				[
					'type' => 'header',
					'data' => [
						'text' => 'Title [dynamic]',
						'level' => 2
					]
				]
			]
		];

		$result = $renderer->render( $data );

		$this->assertStringContainsString( '<h2', $result );
		$this->assertStringContainsString( 'DYNAMIC_CONTENT', $result );
	}

	public function testConstructorWithShortcodeParser(): void
	{
		$shortcodeParser = new ShortcodeParser();
		$renderer = new EditorJsRenderer( $shortcodeParser );

		// Verify it was constructed successfully by using it
		$result = $renderer->render( [ 'blocks' => [] ] );
		$this->assertSame( '', $result );
	}

	public function testRenderEmbedWithSubdomain(): void
	{
		// Test that www.youtube.com (subdomain) is allowed when youtube.com is in whitelist
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'embed' => 'https://www.youtube.com/embed/abc123'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( '<iframe', $result );
		$this->assertStringContainsString( 'www.youtube.com', $result );
	}

	public function testRenderEmbedWithMalformedUrl(): void
	{
		$data = [
			'blocks' => [
				[
					'type' => 'embed',
					'data' => [
						'service' => 'youtube',
						'embed' => 'not-a-valid-url'
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		// Should fail domain validation since parse_url will return empty host
		$this->assertStringContainsString( '<!-- Embed from untrusted domain', $result );
	}

	public function testRenderListWithInvalidItem(): void
	{
		// Test the fallback for invalid list item types (edge case)
		$data = [
			'blocks' => [
				[
					'type' => 'list',
					'data' => [
						'style' => 'unordered',
						'items' => [
							'Valid item',
							123, // Invalid: number instead of string/array
							[ 'invalid' => 'structure' ], // Invalid: array without 'content' key
							'Another valid item'
						]
					]
				]
			]
		];

		$result = $this->renderer->render( $data );

		$this->assertStringContainsString( 'Valid item', $result );
		$this->assertStringContainsString( 'Another valid item', $result );
		$this->assertStringContainsString( '<!-- Invalid list item -->', $result );
	}
}
