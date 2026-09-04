<?php

namespace Tests\Cms\Services\Media;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Search\SearchApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;

/**
 * Unit tests for CloudinaryUploader with mocked Cloudinary API
 */
class CloudinaryUploaderMockTest extends TestCase
{
	private SettingManager $_settings;

	protected function setUp(): void
	{
		parent::setUp();

		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'cloudinary', 'folder', 'test-folder' );

		$this->_settings = new SettingManager( $memory );
	}

	public function testListResourcesCallsSearchApiWithFolderExpression(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [
				[
					'url' => 'https://res.cloudinary.com/test/image/upload/v1/test.jpg',
					'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/test.jpg',
					'public_id' => 'test-folder/test',
					'width' => 800,
					'height' => 600,
					'format' => 'jpg',
					'bytes' => 50000,
					'resource_type' => 'image',
					'created_at' => '2024-01-01T00:00:00Z',
					'tags' => [ 'hero' ],
					'context' => [ 'custom' => [ 'name' => 'Hero Banner' ] ]
				]
			],
			'next_cursor' => 'abc123',
			'total_count' => 10
		], function( string $expression ): void {
			$this->assertStringContainsString( 'folder:"test-folder"', $expression );
			$this->assertStringContainsString( 'asset_folder:"test-folder"', $expression );
			$this->assertStringContainsString( 'resource_type:image', $expression );
		} );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'resources', $result );
		$this->assertArrayHasKey( 'next_cursor', $result );
		$this->assertArrayHasKey( 'total_count', $result );
		$this->assertEquals( 'abc123', $result['next_cursor'] );
		$this->assertEquals( 10, $result['total_count'] );
		$this->assertCount( 1, $result['resources'] );
		$this->assertEquals( 'Hero Banner', $result['resources'][0]['name'] );
		$this->assertEquals( [ 'hero' ], $result['resources'][0]['tags'] );
	}

	public function testListResourcesWithCustomMaxResults(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [],
			'next_cursor' => null,
			'total_count' => 0
		] );
		$searchMock->expects( $this->once() )
			->method( 'maxResults' )
			->with( 10 )
			->willReturnSelf();

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources( [ 'max_results' => 10 ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesWithNextCursor(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [],
			'next_cursor' => null,
			'total_count' => 0
		] );
		$searchMock->expects( $this->once() )
			->method( 'nextCursor' )
			->with( 'cursor123' )
			->willReturnSelf();

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources( [ 'next_cursor' => 'cursor123' ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesWithCustomFolder(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [],
			'next_cursor' => null,
			'total_count' => 0
		], function( string $expression ): void {
			$this->assertStringContainsString( 'folder:"test-folder/blog"', $expression );
		} );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources( [ 'folder' => 'blog' ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesFormatsResultsCorrectly(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [
				[
					'url' => 'http://example.com/image.jpg',
					'secure_url' => 'https://example.com/image.jpg',
					'public_id' => 'test-folder/image',
					'width' => 1024,
					'height' => 768,
					'format' => 'png',
					'bytes' => 100000,
					'resource_type' => 'image',
					'created_at' => '2024-01-15T10:00:00Z'
				]
			],
			'next_cursor' => null,
			'total_count' => 1
		] );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources();

		$this->assertCount( 1, $result['resources'] );
		$resource = $result['resources'][0];

		$this->assertEquals( 'https://example.com/image.jpg', $resource['url'] );
		$this->assertEquals( 'test-folder/image', $resource['public_id'] );
		$this->assertEquals( 'image', $resource['name'] );
		$this->assertEquals( 1024, $resource['width'] );
		$this->assertEquals( 768, $resource['height'] );
		$this->assertEquals( 'png', $resource['format'] );
		$this->assertEquals( 100000, $resource['bytes'] );
		$this->assertEquals( 'image', $resource['resource_type'] );
		$this->assertEquals( '2024-01-15T10:00:00Z', $resource['created_at'] );
		$this->assertTrue( $resource['url_change_on_move'] );
	}

	public function testListResourcesHandlesEmptyResults(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => []
		] );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$result = $uploader->listResources();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['resources'] );
		$this->assertNull( $result['next_cursor'] );
		$this->assertEquals( 0, $result['total_count'] );
	}

	public function testListResourcesThrowsExceptionOnApiFailure(): void
	{
		$searchMock = $this->createSearchApiMock( [] );
		$searchMock->method( 'execute' )
			->willThrowException( new \Exception( 'API Error' ) );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary list resources failed' );

		$uploader->listResources();
	}

	public function testDeleteCallsUploadApiWithPublicId(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		$uploadApiMock->expects( $this->once() )
			->method( 'destroy' )
			->with( 'test-folder/test-image' )
			->willReturn( [ 'result' => 'ok' ] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->delete( 'test-folder/test-image' );

		$this->assertTrue( $result );
	}

	public function testDeleteReturnsFalseOnFailure(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		$uploadApiMock->method( 'destroy' )
			->willReturn( [ 'result' => 'not found' ] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->delete( 'test-folder/nonexistent' );

		$this->assertFalse( $result );
	}

	public function testDeleteThrowsExceptionOnApiError(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		$uploadApiMock->method( 'destroy' )
			->willThrowException( new \Exception( 'API Error' ) );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary deletion failed' );

		$uploader->delete( 'test-folder/test-image' );
	}

	public function testUploadCallsUploadApiWithCorrectOptions(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		// Create a real temporary file
		$tmpFile = tmpfile();
		$tmpFilePath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'fake image content' );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				$tmpFilePath,
				$this->callback( function( $options ) {
					return $options['folder'] === 'test-folder'
						&& $options['resource_type'] === 'image';
				} )
			)
			->willReturn( [
				'secure_url' => 'https://example.com/test.jpg',
				'public_id' => 'test-folder/test',
				'width' => 800,
				'height' => 600,
				'format' => 'jpg',
				'bytes' => 50000
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->upload( $tmpFilePath );

		$this->assertArrayHasKey( 'url', $result );
		$this->assertEquals( 'https://example.com/test.jpg', $result['url'] );

		// Clean up
		fclose( $tmpFile );
	}

	public function testUploadThrowsExceptionForNonexistentFile(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'File not found' );

		$uploader->upload( '/nonexistent/file.jpg' );
	}

	public function testUploadThrowsExceptionOnApiError(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		// Create a real temporary file
		$tmpFile = tmpfile();
		$tmpFilePath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'fake image content' );

		$uploadApiMock->method( 'upload' )
			->willThrowException( new \Exception( 'Upload failed' ) );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary upload failed' );

		$uploader->upload( $tmpFilePath );

		// Clean up
		fclose( $tmpFile );
	}

	public function testUploadWithCustomOptions(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		// Create a real temporary file
		$tmpFile = tmpfile();
		$tmpFilePath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'fake image content' );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				$tmpFilePath,
				$this->callback( function( $options ) {
					return $options['folder'] === 'test-folder/custom-folder'
						&& $options['public_id'] === 'my-image'
						&& isset( $options['tags'] )
						&& in_array( 'test', $options['tags'] );
				} )
			)
			->willReturn( [
				'secure_url' => 'https://example.com/custom.jpg',
				'public_id' => 'custom-folder/my-image',
				'width' => 1024,
				'height' => 768,
				'format' => 'jpg',
				'bytes' => 100000
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->upload( $tmpFilePath, [
			'folder' => 'custom-folder',
			'public_id' => 'my-image',
			'tags' => ['test', 'upload']
		] );

		$this->assertEquals( 'https://example.com/custom.jpg', $result['url'] );
		$this->assertEquals( 'custom-folder/my-image', $result['public_id'] );

		// Clean up
		fclose( $tmpFile );
	}

	public function testUploadWithTransformation(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		// Create a real temporary file
		$tmpFile = tmpfile();
		$tmpFilePath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'fake image content' );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				$tmpFilePath,
				$this->callback( function( $options ) {
					return isset( $options['transformation'] )
						&& is_array( $options['transformation'] );
				} )
			)
			->willReturn( [
				'secure_url' => 'https://example.com/transformed.jpg',
				'public_id' => 'test-folder/transformed',
				'width' => 800,
				'height' => 600,
				'format' => 'jpg',
				'bytes' => 50000
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->upload( $tmpFilePath, [
			'transformation' => [
				'width' => 800,
				'height' => 600,
				'crop' => 'fill'
			]
		] );

		$this->assertArrayHasKey( 'url', $result );

		// Clean up
		fclose( $tmpFile );
	}

	public function testListResourcesFiltersByTag(): void
	{
		$searchMock = $this->createSearchApiMock( [
			'resources' => [],
			'total_count' => 0
		], function( string $expression ): void {
			$this->assertStringContainsString( 'tags="hero"', $expression );
		} );

		$uploader = $this->uploaderWithCloudinary( $this->cloudinaryWithSearch( $searchMock ) );
		$uploader->listResources( [ 'tag' => 'Hero!' ] );
	}

	public function testResolveLibraryFolderRejectsTraversal(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \InvalidArgumentException::class );
		$uploader->resolveLibraryFolder( '../secret' );
	}

	public function testIsLibraryAssetAcceptsDynamicFolderAsset(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->assertTrue( $uploader->isLibraryAsset( 'randomid', 'test-folder' ) );
		$this->assertTrue( $uploader->isLibraryAsset( 'test-folder/image' ) );
		$this->assertFalse( $uploader->isLibraryAsset( 'other-folder/image' ) );
		$this->assertFalse( $uploader->isLibraryAsset( 'randomid' ) );
	}

	public function testSanitizeTagsStripsUnsafeCharacters(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->assertEquals( [ 'hero', 'blog_1', 'badtag' ], $uploader->sanitizeTags( 'Hero, blog_1, bad tag!' ) );
		$this->assertEquals( [], $uploader->sanitizeTags( '!!!,   ' ) );
	}

	public function testListFoldersReturnsSafeSubfolders(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );
		$adminApiMock->expects( $this->once() )
			->method( 'subFolders' )
			->with( 'test-folder' )
			->willReturn( [
				'folders' => [
					[ 'name' => 'blog', 'path' => 'test-folder/blog' ],
					[ 'name' => 'escape', 'path' => '../escape' ]
				]
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$folders = $uploader->listFolders();

		$this->assertCount( 1, $folders );
		$this->assertEquals( 'blog', $folders[0]['name'] );
		$this->assertEquals( 'test-folder/blog', $folders[0]['path'] );
	}

	public function testCreateFolderRejectsRootOnly(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \InvalidArgumentException::class );
		$uploader->createFolder( 'test-folder' );
	}

	public function testCreateFolderCallsAdminApi(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );
		$adminApiMock->expects( $this->once() )
			->method( 'createFolder' )
			->with( 'test-folder/blog' )
			->willReturn( [ 'name' => 'blog', 'path' => 'test-folder/blog' ] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$result = $uploader->createFolder( 'blog' );

		$this->assertEquals( 'test-folder/blog', $result['path'] );
	}

	public function testUpdateResourceSetsNameAndTags(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );
		$adminApiMock->expects( $this->once() )
			->method( 'update' )
			->with(
				'test-folder/image',
				$this->callback( function( $options ) {
					return $options['tags'] === [ 'hero' ]
						&& $options['context']['name'] === 'Hero'
						&& $options['display_name'] === 'Hero';
				} )
			)
			->willReturn( [
				'public_id' => 'test-folder/image',
				'secure_url' => 'https://example.com/image.jpg',
				'display_name' => 'Hero',
				'tags' => [ 'hero' ]
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$result = $uploader->updateResource( 'test-folder/image', [
			'name' => 'Hero',
			'tags' => 'Hero'
		] );

		$this->assertEquals( 'Hero', $result['name'] );
		$this->assertEquals( [ 'hero' ], $result['tags'] );
	}

	public function testMoveResourceRenamesFolderPrefixedPublicId(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );
		$uploadApiMock->expects( $this->once() )
			->method( 'rename' )
			->with( 'test-folder/image', 'test-folder/blog/image', [ 'invalidate' => true ] )
			->willReturn( [
				'public_id' => 'test-folder/blog/image',
				'secure_url' => 'https://example.com/blog/image.jpg'
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$result = $uploader->moveResource( 'test-folder/image', 'blog' );

		$this->assertTrue( $result['url_changed'] );
		$this->assertEquals( 'test-folder/blog/image', $result['public_id'] );
	}

	public function testMoveResourceUpdatesAssetFolderWhenUrlSafe(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );
		$adminApiMock->expects( $this->once() )
			->method( 'update' )
			->with( 'randomid', [ 'asset_folder' => 'test-folder/blog' ] )
			->willReturn( [
				'public_id' => 'randomid',
				'asset_folder' => 'test-folder/blog',
				'secure_url' => 'https://example.com/randomid.jpg'
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$result = $uploader->moveResource( 'randomid', 'blog' );

		$this->assertFalse( $result['url_changed'] );
		$this->assertEquals( 'test-folder/blog', $result['asset_folder'] );
	}

	public function testUploadUsesFilenameWhenNoNameProvided(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );
		$tmpFile = tmpfile();
		$tmpFilePath = stream_get_meta_data( $tmpFile )['uri'];
		fwrite( $tmpFile, 'fake image content' );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				$tmpFilePath,
				$this->callback( function( $options ) {
					return !empty( $options['use_filename'] )
						&& $options['unique_filename'] === true
						&& $options['folder'] === 'test-folder';
				} )
			)
			->willReturn( [
				'secure_url' => 'https://example.com/test.jpg',
				'public_id' => 'test-folder/original-name'
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'uploadApi' )->willReturn( $uploadApiMock );

		$uploader = $this->uploaderWithCloudinary( $cloudinaryMock );
		$uploader->upload( $tmpFilePath );

		fclose( $tmpFile );
	}

	private function createSearchApiMock( array $result, ?callable $expressionAssert = null ): SearchApi
	{
		$searchMock = $this->createMock( SearchApi::class );
		$searchMock->method( 'expression' )
			->willReturnCallback( function( $expression ) use ( $searchMock, $expressionAssert ) {
				if( $expressionAssert )
				{
					$expressionAssert( $expression );
				}

				return $searchMock;
			} );
		$searchMock->method( 'withField' )->willReturnSelf();
		$searchMock->method( 'maxResults' )->willReturnSelf();
		$searchMock->method( 'sortBy' )->willReturnSelf();
		$searchMock->method( 'nextCursor' )->willReturnSelf();
		$searchMock->method( 'execute' )->willReturn( $result );

		return $searchMock;
	}

	private function cloudinaryWithSearch( SearchApi $searchMock ): Cloudinary
	{
		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'searchApi' )->willReturn( $searchMock );

		return $cloudinaryMock;
	}

	private function uploaderWithCloudinary( Cloudinary $cloudinaryMock ): CloudinaryUploader
	{
		$uploader = new CloudinaryUploader( $this->_settings );
		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		return $uploader;
	}
}
