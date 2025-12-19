<?php

namespace Tests\Cms\Services\Media;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;

/**
 * Test CloudinaryUploader service
 */
class CloudinaryUploaderTest extends TestCase
{
	private SettingManager $_settings;

	protected function setUp(): void
	{
		parent::setUp();

		// Create in-memory settings for testing
		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'cloudinary', 'folder', 'test-folder' );

		$this->_settings = new SettingManager( $memory );
	}

	public function testConstructorThrowsExceptionWithMissingConfig(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary configuration is incomplete' );

		// Create settings without cloudinary config
		$memory = new Memory();
		$settings = new SettingManager( $memory );

		new CloudinaryUploader( $settings );
	}

	public function testUploadThrowsExceptionForNonExistentFile(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'File not found' );

		$uploader->upload( '/path/to/nonexistent/file.jpg' );
	}

	public function testUploadFromUrlThrowsExceptionForInvalidUrl(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid URL' );

		$uploader->uploadFromUrl( 'not-a-valid-url' );
	}

	public function testUploadFromUrlRejectsHttpUrls(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs are allowed' );

		$uploader->uploadFromUrl( 'http://example.com/image.jpg' );
	}

	public function testUploadFromUrlRejectsFtpUrls(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs are allowed' );

		$uploader->uploadFromUrl( 'ftp://example.com/image.jpg' );
	}

	public function testUploadFromUrlRejectsLocalhostUrls(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		// DNS resolution behavior for 'localhost' varies by system
		// Either: "Unable to resolve" or "private or reserved IP address"
		// Both outcomes mean localhost is blocked, which is the security goal
		$this->expectExceptionMessageMatches( '/(Unable to resolve hostname|private or reserved IP address)/' );

		// localhost should resolve to 127.0.0.1 which is loopback
		$uploader->uploadFromUrl( 'https://localhost/image.jpg' );
	}

	public function testUploadFromUrlRejectsLoopbackIpv4(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://127.0.0.1/image.jpg' );
	}

	public function testUploadFromUrlRejectsPrivateIpRange10(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://10.0.0.1/image.jpg' );
	}

	public function testUploadFromUrlRejectsPrivateIpRange192(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://192.168.1.1/image.jpg' );
	}

	public function testUploadFromUrlRejectsPrivateIpRange172(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		$uploader->uploadFromUrl( 'https://172.16.0.1/image.jpg' );
	}

	public function testUploadFromUrlRejectsLinkLocalIp(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		// 169.254.0.0/16 is link-local (APIPA)
		$uploader->uploadFromUrl( 'https://169.254.169.254/image.jpg' );
	}

	public function testUploadFromUrlRejectsCloudMetadataIp(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'private or reserved IP address' );

		// 169.254.169.254 is commonly used for cloud metadata services
		$uploader->uploadFromUrl( 'https://169.254.169.254/latest/meta-data/' );
	}

	/**
	 * Note: The following tests require actual Cloudinary credentials
	 * and are marked as incomplete. They can be enabled for integration testing.
	 */

	public function testUploadWithValidFile(): void
	{
		$this->markTestIncomplete(
			'This test requires valid Cloudinary credentials and a test image file. ' .
			'Enable for integration testing.'
		);

		// Example integration test:
		// $uploader = new CloudinaryUploader( $this->_settings );
		// $result = $uploader->upload( '/path/to/test/image.jpg' );
		//
		// $this->assertIsArray( $result );
		// $this->assertArrayHasKey( 'url', $result );
		// $this->assertArrayHasKey( 'public_id', $result );
		// $this->assertArrayHasKey( 'width', $result );
		// $this->assertArrayHasKey( 'height', $result );
	}

	public function testUploadFromUrlWithValidUrl(): void
	{
		$this->markTestIncomplete(
			'This test requires valid Cloudinary credentials and internet connection. ' .
			'Enable for integration testing.'
		);

		// Example integration test:
		// $uploader = new CloudinaryUploader( $this->_settings );
		// $result = $uploader->uploadFromUrl( 'https://example.com/test-image.jpg' );
		//
		// $this->assertIsArray( $result );
		// $this->assertArrayHasKey( 'url', $result );
	}

	public function testDeleteWithValidPublicId(): void
	{
		$this->markTestIncomplete(
			'This test requires valid Cloudinary credentials. ' .
			'Enable for integration testing.'
		);

		// Example integration test:
		// $uploader = new CloudinaryUploader( $this->_settings );
		// $result = $uploader->delete( 'test-folder/test-image' );
		//
		// $this->assertIsBool( $result );
	}
}
