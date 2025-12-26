<?php

namespace Tests\Cms\Services\Media;

use Cloudinary\Api\Admin\AdminApi;
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

	public function testListResourcesCallsAdminApiWithCorrectParameters(): void
	{
		// Create a mock AdminApi
		$adminApiMock = $this->createMock( AdminApi::class );

		// Expect assets() to be called with correct parameters
		$adminApiMock->expects( $this->once() )
			->method( 'assets' )
			->with( [
				'type' => 'upload',
				'prefix' => 'test-folder',
				'max_results' => 30,
				'resource_type' => 'image'
			] )
			->willReturn( [
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
						'created_at' => '2024-01-01T00:00:00Z'
					]
				],
				'next_cursor' => 'abc123',
				'total_count' => 10
			] );

		// Create a mock Cloudinary instance
		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )
			->willReturn( $adminApiMock );

		// Create uploader and inject mock via reflection
		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		// Call listResources
		$result = $uploader->listResources();

		// Verify structure
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'resources', $result );
		$this->assertArrayHasKey( 'next_cursor', $result );
		$this->assertArrayHasKey( 'total_count', $result );
		$this->assertEquals( 'abc123', $result['next_cursor'] );
		$this->assertEquals( 10, $result['total_count'] );
		$this->assertCount( 1, $result['resources'] );
	}

	public function testListResourcesWithCustomMaxResults(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->expects( $this->once() )
			->method( 'assets' )
			->with( $this->callback( function( $options ) {
				return $options['max_results'] === 10;
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->listResources( [ 'max_results' => 10 ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesWithNextCursor(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->expects( $this->once() )
			->method( 'assets' )
			->with( $this->callback( function( $options ) {
				return isset( $options['next_cursor'] ) && $options['next_cursor'] === 'cursor123';
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->listResources( [ 'next_cursor' => 'cursor123' ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesWithCustomFolder(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->expects( $this->once() )
			->method( 'assets' )
			->with( $this->callback( function( $options ) {
				return $options['prefix'] === 'custom-folder';
			} ) )
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->listResources( [ 'folder' => 'custom-folder' ] );

		$this->assertIsArray( $result );
	}

	public function testListResourcesFormatsResultsCorrectly(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->method( 'assets' )
			->willReturn( [
				'resources' => [
					[
						'url' => 'http://example.com/image.jpg',
						'secure_url' => 'https://example.com/image.jpg',
						'public_id' => 'test/image',
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

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->listResources();

		$this->assertCount( 1, $result['resources'] );
		$resource = $result['resources'][0];

		// Verify formatted result structure
		$this->assertEquals( 'https://example.com/image.jpg', $resource['url'] );
		$this->assertEquals( 'test/image', $resource['public_id'] );
		$this->assertEquals( 1024, $resource['width'] );
		$this->assertEquals( 768, $resource['height'] );
		$this->assertEquals( 'png', $resource['format'] );
		$this->assertEquals( 100000, $resource['bytes'] );
		$this->assertEquals( 'image', $resource['resource_type'] );
		$this->assertEquals( '2024-01-15T10:00:00Z', $resource['created_at'] );
	}

	public function testListResourcesHandlesEmptyResults(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->method( 'assets' )
			->willReturn( [
				'resources' => [],
				'total_count' => 0
			] );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

		$result = $uploader->listResources();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['resources'] );
		$this->assertNull( $result['next_cursor'] );
		$this->assertEquals( 0, $result['total_count'] );
	}

	public function testListResourcesThrowsExceptionOnApiFailure(): void
	{
		$adminApiMock = $this->createMock( AdminApi::class );

		$adminApiMock->method( 'assets' )
			->willThrowException( new \Exception( 'API Error' ) );

		$cloudinaryMock = $this->createMock( Cloudinary::class );
		$cloudinaryMock->method( 'adminApi' )->willReturn( $adminApiMock );

		$uploader = new CloudinaryUploader( $this->_settings );

		$reflection = new \ReflectionClass( $uploader );
		$cloudinaryProperty = $reflection->getProperty( '_cloudinary' );
		$cloudinaryProperty->setAccessible( true );
		$cloudinaryProperty->setValue( $uploader, $cloudinaryMock );

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
		$cloudinaryProperty->setAccessible( true );
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
		$cloudinaryProperty->setAccessible( true );
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
		$cloudinaryProperty->setAccessible( true );
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
		$cloudinaryProperty->setAccessible( true );
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
		$cloudinaryProperty->setAccessible( true );
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
					return $options['folder'] === 'custom-folder'
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
		$cloudinaryProperty->setAccessible( true );
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
		$cloudinaryProperty->setAccessible( true );
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
}
