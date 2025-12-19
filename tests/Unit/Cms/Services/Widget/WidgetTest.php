<?php

namespace Tests\Cms\Services\Widget;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Widget\Widget;

// Create a concrete test implementation of the abstract Widget class
class TestWidget extends Widget
{
	public function getName(): string
	{
		return 'test-widget';
	}

	public function render( array $attrs ): string
	{
		return '<div>Test Widget</div>';
	}

	// Expose protected methods for testing
	public function testAttr( array $attrs, string $key, mixed $default = null ): mixed
	{
		return $this->attr( $attrs, $key, $default );
	}

	public function testView( string $template, array $data = [] ): string
	{
		return $this->view( $template, $data );
	}

	public function testSanitizeHtml( string $html ): string
	{
		return $this->sanitizeHtml( $html );
	}
}

class WidgetTest extends TestCase
{
	private TestWidget $widget;

	protected function setUp(): void
	{
		parent::setUp();
		$this->widget = new TestWidget();
	}

	public function testGetDescriptionReturnsEmptyStringByDefault(): void
	{
		$result = $this->widget->getDescription();
		$this->assertEquals( '', $result );
	}

	public function testGetAttributesReturnsEmptyArrayByDefault(): void
	{
		$result = $this->widget->getAttributes();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function testAttrReturnsValueWhenKeyExists(): void
	{
		$attrs = ['title' => 'Test Title', 'count' => 5];

		$this->assertEquals( 'Test Title', $this->widget->testAttr( $attrs, 'title' ) );
		$this->assertEquals( 5, $this->widget->testAttr( $attrs, 'count' ) );
	}

	public function testAttrReturnsDefaultWhenKeyDoesNotExist(): void
	{
		$attrs = ['title' => 'Test'];

		$this->assertEquals( 'default', $this->widget->testAttr( $attrs, 'missing', 'default' ) );
		$this->assertNull( $this->widget->testAttr( $attrs, 'missing' ) );
	}

	public function testAttrReturnsNullByDefaultWhenKeyDoesNotExist(): void
	{
		$attrs = [];

		$result = $this->widget->testAttr( $attrs, 'nonexistent' );
		$this->assertNull( $result );
	}

	public function testViewReturnsCommentWhenTemplateNotFound(): void
	{
		$result = $this->widget->testView( '/nonexistent/template.php' );

		$this->assertStringContainsString( '<!-- Template not found:', $result );
		$this->assertStringContainsString( '/nonexistent/template.php', $result );
	}

	public function testViewRendersTemplateWithData(): void
	{
		// Create a temporary template file
		$tempFile = tempnam( sys_get_temp_dir(), 'widget_test_' );
		file_put_contents( $tempFile, '<?php echo "Hello, {$name}!"; ?>' );

		$result = $this->widget->testView( $tempFile, ['name' => 'World'] );

		$this->assertEquals( 'Hello, World!', $result );

		// Clean up
		unlink( $tempFile );
	}

	public function testViewRendersTemplateWithoutData(): void
	{
		// Create a temporary template file
		$tempFile = tempnam( sys_get_temp_dir(), 'widget_test_' );
		file_put_contents( $tempFile, 'Static content' );

		$result = $this->widget->testView( $tempFile );

		$this->assertEquals( 'Static content', $result );

		// Clean up
		unlink( $tempFile );
	}

	public function testSanitizeHtmlAllowsSafeTags(): void
	{
		$html = '<div><p>Hello <strong>World</strong>!</p></div>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertEquals( '<div><p>Hello <strong>World</strong>!</p></div>', $result );
	}

	public function testSanitizeHtmlRemovesScriptTags(): void
	{
		$html = '<div>Safe content <script>alert("XSS")</script></div>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
		$this->assertStringContainsString( 'Safe content', $result );
		// Note: strip_tags removes tags but keeps content, so "alert" will still be present
	}

	public function testSanitizeHtmlRemovesDangerousTags(): void
	{
		$html = '<div>Content</div><iframe src="evil.com"></iframe>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertStringNotContainsString( '<iframe>', $result );
		$this->assertStringContainsString( '<div>Content</div>', $result );
	}

	public function testSanitizeHtmlAllowsLinks(): void
	{
		$html = '<a href="https://example.com">Link</a>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertEquals( '<a href="https://example.com">Link</a>', $result );
	}

	public function testSanitizeHtmlAllowsImages(): void
	{
		$html = '<img src="image.jpg" alt="Test">';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertStringContainsString( '<img', $result );
	}

	public function testSanitizeHtmlAllowsHeadings(): void
	{
		$html = '<h1>Title</h1><h2>Subtitle</h2>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertEquals( '<h1>Title</h1><h2>Subtitle</h2>', $result );
	}

	public function testSanitizeHtmlAllowsLists(): void
	{
		$html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
		$result = $this->widget->testSanitizeHtml( $html );

		$this->assertEquals( '<ul><li>Item 1</li><li>Item 2</li></ul>', $result );
	}
}
