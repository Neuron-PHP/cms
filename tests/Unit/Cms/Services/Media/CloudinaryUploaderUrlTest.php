<?php

namespace Tests\Cms\Services\Media;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;

/**
 * Unit tests for CloudinaryUploader uploadFromUrl method
 */
class CloudinaryUploaderUrlTest extends TestCase
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

	public function testUploadFromUrlThrowsExceptionForInvalidUrl(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid URL' );

		$uploader->uploadFromUrl( 'not-a-url' );
	}

	public function testUploadFromUrlThrowsExceptionForHttpUrl(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs are allowed' );

		$uploader->uploadFromUrl( 'http://example.com/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForFtpUrl(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs are allowed' );

		$uploader->uploadFromUrl( 'ftp://example.com/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForLoopbackIp(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://127.0.0.1/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForPrivateIp10(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://10.0.0.1/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForPrivateIp192(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://192.168.1.1/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForPrivateIp172(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://172.16.0.1/image.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForLinkLocalIp(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		// 169.254.169.254 is cloud metadata service IP
		$uploader->uploadFromUrl( 'https://169.254.169.254/latest/meta-data/' );
	}

	public function testUploadFromUrlCallsUploadApiWithValidUrl(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				'https://example.com/test.jpg',
				$this->callback( function( $options ) {
					return $options['folder'] === 'test-folder'
						&& $options['resource_type'] === 'image';
				} )
			)
			->willReturn( [
				'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/test.jpg',
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

		$result = $uploader->uploadFromUrl( 'https://example.com/test.jpg' );

		$this->assertArrayHasKey( 'url', $result );
		$this->assertEquals( 'https://res.cloudinary.com/test/image/upload/v1/test.jpg', $result['url'] );
	}

	public function testUploadFromUrlWithCustomOptions(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

		$uploadApiMock->expects( $this->once() )
			->method( 'upload' )
			->with(
				'https://example.com/test.jpg',
				$this->callback( function( $options ) {
					return $options['folder'] === 'custom'
						&& isset( $options['public_id'] );
				} )
			)
			->willReturn( [
				'secure_url' => 'https://res.cloudinary.com/test/image/upload/v1/custom.jpg',
				'public_id' => 'custom/my-image',
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

		$result = $uploader->uploadFromUrl( 'https://example.com/test.jpg', [
			'folder' => 'custom',
			'public_id' => 'my-image'
		] );

		$this->assertArrayHasKey( 'url', $result );
	}

	public function testUploadFromUrlThrowsExceptionOnApiError(): void
	{
		$uploadApiMock = $this->createMock( UploadApi::class );

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
		$this->expectExceptionMessage( 'Cloudinary upload from URL failed' );

		$uploader->uploadFromUrl( 'https://example.com/test.jpg' );
	}
}
