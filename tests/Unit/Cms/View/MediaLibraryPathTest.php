<?php

namespace Tests\Cms\View;

use PHPUnit\Framework\TestCase;

/**
 * Test media library folder URLs used by breadcrumbs and parent links.
 */
class MediaLibraryPathTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once __DIR__ . '/../../../../src/Cms/View/helpers.php';
	}

	public function testRootFolderLinkIsNeverEmpty(): void
	{
		$path = media_library_path( 'neuron-cms/images', 'neuron-cms/images' );

		$this->assertNotSame( '', $path );
		$this->assertStringNotContainsString( 'folder=', $path );
	}

	public function testSubfolderLinkIncludesFolderQuery(): void
	{
		$path = media_library_path( 'neuron-cms/images', 'neuron-cms/images/blog' );

		$this->assertStringContainsString( 'folder=', $path );
		parse_str( (string) parse_url( $path, PHP_URL_QUERY ), $query );
		$this->assertEquals( 'neuron-cms/images/blog', $query['folder'] ?? null );
	}

	public function testParentOfSubfolderUsesRootUrl(): void
	{
		$root = 'neuron-cms/images';
		$current = 'neuron-cms/images/blog';
		$parent = dirname( $current );

		$this->assertSame( $root, $parent );
		$this->assertSame(
			media_library_path( $root, $root ),
			media_library_path( $root, $parent )
		);
	}

	public function testTagFilterIsPreserved(): void
	{
		$path = media_library_path( 'neuron-cms/images', 'neuron-cms/images', 'hero' );

		$this->assertStringContainsString( 'tag=hero', $path );
		$this->assertStringNotContainsString( 'folder=', $path );
	}
}
