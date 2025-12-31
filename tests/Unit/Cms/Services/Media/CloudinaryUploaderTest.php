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

		// Load .env.testing file if it exists (for local testing with real credentials)
		$envFile = __DIR__ . '/../../../../../.env.testing';
		if( file_exists( $envFile ) )
		{
			$this->loadEnvFile( $envFile );
		}

		// Create settings from environment variables if available, otherwise use test values
		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', getenv( 'CLOUDINARY_CLOUD_NAME' ) ?: 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', getenv( 'CLOUDINARY_API_KEY' ) ?: 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', getenv( 'CLOUDINARY_API_SECRET' ) ?: 'test-secret' );
		$memory->set( 'cloudinary', 'folder', getenv( 'CLOUDINARY_FOLDER' ) ?: 'test-folder' );

		$this->_settings = new SettingManager( $memory );
	}

	/**
	 * Load environment variables from a .env file
	 */
	private function loadEnvFile( string $filePath ): void
	{
		if( !file_exists( $filePath ) )
		{
			return;
		}

		$lines = file( $filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		foreach( $lines as $line )
		{
			// Skip comments and empty lines
			if( strpos( trim( $line ), '#' ) === 0 || trim( $line ) === '' )
			{
				continue;
			}

			// Parse KEY=VALUE format
			if( strpos( $line, '=' ) !== false )
			{
				list( $key, $value ) = explode( '=', $line, 2 );
				$key = trim( $key );
				$value = trim( $value );

				// Remove quotes if present
				if( preg_match( '/^["\'](.*)["\']\s*$/', $value, $matches ) )
				{
					$value = $matches[1];
				}

				putenv( "$key=$value" );
				$_ENV[$key] = $value;
				$_SERVER[$key] = $value;
			}
		}
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
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		// Integration test requires actual file and credentials
		$testImagePath = __DIR__ . '/../../../../resources/test-fixtures/test-image.jpg';
		if( !file_exists( $testImagePath ) )
		{
			$this->markTestSkipped( 'Test image file not found: ' . $testImagePath );
		}

		$uploader = new CloudinaryUploader( $this->_settings );
		$result = $uploader->upload( $testImagePath );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'public_id', $result );
		$this->assertArrayHasKey( 'width', $result );
		$this->assertArrayHasKey( 'height', $result );
	}

	public function testUploadFromUrlWithValidUrl(): void
	{
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		$uploader = new CloudinaryUploader( $this->_settings );
		// Using a reliable test image URL
		$result = $uploader->uploadFromUrl( 'https://via.placeholder.com/150' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'public_id', $result );
	}

	public function testDeleteWithValidPublicId(): void
	{
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		// Note: This test assumes a test image exists with this public_id
		// In a real integration test, you would upload first, then delete
		$uploader = new CloudinaryUploader( $this->_settings );
		$result = $uploader->delete( 'test-folder/test-image' );

		$this->assertIsBool( $result );
	}

	public function testListResourcesReturnsExpectedStructure(): void
	{
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		$uploader = new CloudinaryUploader( $this->_settings );
		$result = $uploader->listResources();

		// Verify structure
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'resources', $result );
		$this->assertArrayHasKey( 'next_cursor', $result );
		$this->assertArrayHasKey( 'total_count', $result );

		// Verify resources is an array
		$this->assertIsArray( $result['resources'] );

		// If there are resources, verify their structure
		if( !empty( $result['resources'] ) )
		{
			$resource = $result['resources'][0];
			$this->assertArrayHasKey( 'url', $resource );
			$this->assertArrayHasKey( 'public_id', $resource );
			$this->assertArrayHasKey( 'width', $resource );
			$this->assertArrayHasKey( 'height', $resource );
			$this->assertArrayHasKey( 'format', $resource );
			$this->assertArrayHasKey( 'bytes', $resource );
		}
	}

	public function testListResourcesWithCustomOptions(): void
	{
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		$uploader = new CloudinaryUploader( $this->_settings );
		$result = $uploader->listResources( [
			'max_results' => 5
		] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'resources', $result );

		// If there are resources, should not exceed max_results
		if( !empty( $result['resources'] ) )
		{
			$this->assertLessThanOrEqual( 5, count( $result['resources'] ) );
		}
	}

	public function testListResourcesWithPagination(): void
	{
		// Skip if using test credentials (not real Cloudinary account)
		$cloudName = $this->_settings->get( 'cloudinary', 'cloud_name' );
		$isTestCredentials = ($cloudName === 'test-cloud');
		$hasRealCredentials = getenv( 'CLOUDINARY_URL' ) || (!$isTestCredentials && $cloudName);

		if( !$hasRealCredentials )
		{
			$this->markTestSkipped(
				'Cloudinary credentials not configured. Set CLOUDINARY_URL environment variable or configure real cloudinary settings to run this integration test.'
			);
		}

		$uploader = new CloudinaryUploader( $this->_settings );

		// Get first page
		$firstPage = $uploader->listResources( [ 'max_results' => 2 ] );

		// If we have a next_cursor, test pagination
		if( $firstPage['next_cursor'] )
		{
			$secondPage = $uploader->listResources( [
				'max_results' => 2,
				'next_cursor' => $firstPage['next_cursor']
			] );

			$this->assertIsArray( $secondPage );
			$this->assertArrayHasKey( 'resources', $secondPage );

			// Resources should be different
			if( !empty( $firstPage['resources'] ) && !empty( $secondPage['resources'] ) )
			{
				$this->assertNotEquals(
					$firstPage['resources'][0]['public_id'],
					$secondPage['resources'][0]['public_id']
				);
			}
		}
		else
		{
			// If no next_cursor, we just verify structure is correct
			$this->assertNull( $firstPage['next_cursor'] );
		}
	}

	/**
	 * Unit tests using mocked Cloudinary SDK to test logic without real API calls
	 */

	public function testUploadCallsCloudinaryApiCorrectly(): void
	{
		// Create a temporary test file
		$testFile = tempnam( sys_get_temp_dir(), 'test_image_' );
		file_put_contents( $testFile, 'test image content' );

		try
		{
			$uploader = new CloudinaryUploader( $this->_settings );

			// Mock the Cloudinary instance
			$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
			$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

			// Set up the mock expectations
			$mockCloudinary
				->expects( $this->once() )
				->method( 'uploadApi' )
				->willReturn( $mockUploadApi );

			$mockUploadApi
				->expects( $this->once() )
				->method( 'upload' )
				->with(
					$this->equalTo( $testFile ),
					$this->callback( function( $options ) {
						return isset( $options['folder'] ) &&
						       $options['folder'] === 'test-folder' &&
						       isset( $options['resource_type'] ) &&
						       $options['resource_type'] === 'image';
					} )
				)
				->willReturn( [
					'secure_url' => 'https://res.cloudinary.com/test/image.jpg',
					'public_id' => 'test-folder/image',
					'width' => 800,
					'height' => 600,
					'format' => 'jpg',
					'bytes' => 12345,
					'resource_type' => 'image',
					'created_at' => '2024-01-01T00:00:00Z'
				] );

			// Use reflection to inject the mock
			$reflection = new \ReflectionClass( $uploader );
			$property = $reflection->getProperty( '_cloudinary' );
			$property->setAccessible( true );
			$property->setValue( $uploader, $mockCloudinary );

			// Test the upload
			$result = $uploader->upload( $testFile );

			// Verify the result format
			$this->assertIsArray( $result );
			$this->assertEquals( 'https://res.cloudinary.com/test/image.jpg', $result['url'] );
			$this->assertEquals( 'test-folder/image', $result['public_id'] );
			$this->assertEquals( 800, $result['width'] );
			$this->assertEquals( 600, $result['height'] );
			$this->assertEquals( 'jpg', $result['format'] );
			$this->assertEquals( 12345, $result['bytes'] );
		}
		finally
		{
			// Clean up
			if( file_exists( $testFile ) )
			{
				unlink( $testFile );
			}
		}
	}

	public function testUploadWithCustomOptions(): void
	{
		// Create a temporary test file
		$testFile = tempnam( sys_get_temp_dir(), 'test_image_' );
		file_put_contents( $testFile, 'test image content' );

		try
		{
			$uploader = new CloudinaryUploader( $this->_settings );

			// Mock the Cloudinary instance
			$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
			$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

			$mockCloudinary
				->method( 'uploadApi' )
				->willReturn( $mockUploadApi );

			$mockUploadApi
				->expects( $this->once() )
				->method( 'upload' )
				->with(
					$this->equalTo( $testFile ),
					$this->callback( function( $options ) {
						return $options['folder'] === 'custom-folder' &&
						       $options['public_id'] === 'my-image' &&
						       isset( $options['tags'] ) &&
						       in_array( 'test-tag', $options['tags'] );
					} )
				)
				->willReturn( [
					'secure_url' => 'https://res.cloudinary.com/test/custom-folder/my-image.jpg',
					'public_id' => 'custom-folder/my-image',
					'width' => 1024,
					'height' => 768,
					'format' => 'jpg'
				] );

			// Use reflection to inject the mock
			$reflection = new \ReflectionClass( $uploader );
			$property = $reflection->getProperty( '_cloudinary' );
			$property->setAccessible( true );
			$property->setValue( $uploader, $mockCloudinary );

			// Test upload with custom options
			$result = $uploader->upload( $testFile, [
				'folder' => 'custom-folder',
				'public_id' => 'my-image',
				'tags' => [ 'test-tag', 'another-tag' ]
			] );

			$this->assertEquals( 'custom-folder/my-image', $result['public_id'] );
		}
		finally
		{
			if( file_exists( $testFile ) )
			{
				unlink( $testFile );
			}
		}
	}

	public function testUploadFromUrlCallsCloudinaryApiCorrectly(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

		$mockCloudinary
			->expects( $this->once() )
			->method( 'uploadApi' )
			->willReturn( $mockUploadApi );

		$mockUploadApi
			->expects( $this->once() )
			->method( 'upload' )
			->with(
				$this->equalTo( 'https://example.com/image.jpg' ),
				$this->callback( function( $options ) {
					return isset( $options['folder'] ) &&
					       $options['folder'] === 'test-folder';
				} )
			)
			->willReturn( [
				'secure_url' => 'https://res.cloudinary.com/test/image.jpg',
				'public_id' => 'test-folder/image',
				'width' => 800,
				'height' => 600,
				'format' => 'jpg'
			] );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		// Test uploadFromUrl
		$result = $uploader->uploadFromUrl( 'https://example.com/image.jpg' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://res.cloudinary.com/test/image.jpg', $result['url'] );
		$this->assertEquals( 'test-folder/image', $result['public_id'] );
	}

	public function testDeleteCallsCloudinaryApiCorrectly(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

		$mockCloudinary
			->expects( $this->once() )
			->method( 'uploadApi' )
			->willReturn( $mockUploadApi );

		$mockUploadApi
			->expects( $this->once() )
			->method( 'destroy' )
			->with( $this->equalTo( 'test-folder/image-to-delete' ) )
			->willReturn( [ 'result' => 'ok' ] );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		// Test delete
		$result = $uploader->delete( 'test-folder/image-to-delete' );

		$this->assertTrue( $result );
	}

	public function testDeleteReturnsFalseWhenResultIsNotOk(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

		$mockCloudinary
			->method( 'uploadApi' )
			->willReturn( $mockUploadApi );

		$mockUploadApi
			->method( 'destroy' )
			->willReturn( [ 'result' => 'not found' ] );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		// Test delete with non-ok result
		$result = $uploader->delete( 'nonexistent-image' );

		$this->assertFalse( $result );
	}

	public function testListResourcesCallsCloudinaryApiCorrectly(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockAdminApi = $this->createMock( \Cloudinary\Api\Admin\AdminApi::class );

		$mockCloudinary
			->expects( $this->once() )
			->method( 'adminApi' )
			->willReturn( $mockAdminApi );

		$mockAdminApi
			->expects( $this->once() )
			->method( 'assets' )
			->with(
				$this->callback( function( $options ) {
					return $options['type'] === 'upload' &&
					       $options['prefix'] === 'test-folder' &&
					       $options['max_results'] === 30 &&
					       $options['resource_type'] === 'image';
				} )
			)
			->willReturn( [
				'resources' => [
					[
						'secure_url' => 'https://res.cloudinary.com/test/image1.jpg',
						'public_id' => 'test-folder/image1',
						'width' => 800,
						'height' => 600,
						'format' => 'jpg',
						'bytes' => 12345,
						'resource_type' => 'image',
						'created_at' => '2024-01-01T00:00:00Z'
					],
					[
						'secure_url' => 'https://res.cloudinary.com/test/image2.jpg',
						'public_id' => 'test-folder/image2',
						'width' => 1024,
						'height' => 768,
						'format' => 'jpg',
						'bytes' => 23456,
						'resource_type' => 'image',
						'created_at' => '2024-01-02T00:00:00Z'
					]
				],
				'next_cursor' => 'abc123',
				'total_count' => 100
			] );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		// Test listResources
		$result = $uploader->listResources();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'resources', $result );
		$this->assertArrayHasKey( 'next_cursor', $result );
		$this->assertArrayHasKey( 'total_count', $result );
		$this->assertCount( 2, $result['resources'] );
		$this->assertEquals( 'abc123', $result['next_cursor'] );
		$this->assertEquals( 100, $result['total_count'] );
		$this->assertEquals( 'test-folder/image1', $result['resources'][0]['public_id'] );
	}

	public function testListResourcesWithCustomOptionsAndPagination(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockAdminApi = $this->createMock( \Cloudinary\Api\Admin\AdminApi::class );

		$mockCloudinary
			->method( 'adminApi' )
			->willReturn( $mockAdminApi );

		$mockAdminApi
			->expects( $this->once() )
			->method( 'assets' )
			->with(
				$this->callback( function( $options ) {
					return $options['max_results'] === 10 &&
					       $options['next_cursor'] === 'cursor123' &&
					       $options['prefix'] === 'custom-folder';
				} )
			)
			->willReturn( [
				'resources' => [],
				'next_cursor' => null,
				'total_count' => 0
			] );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		// Test listResources with custom options
		$result = $uploader->listResources( [
			'max_results' => 10,
			'next_cursor' => 'cursor123',
			'folder' => 'custom-folder'
		] );

		$this->assertIsArray( $result );
		$this->assertNull( $result['next_cursor'] );
	}

	public function testUploadThrowsExceptionOnCloudinaryError(): void
	{
		$testFile = tempnam( sys_get_temp_dir(), 'test_image_' );
		file_put_contents( $testFile, 'test content' );

		try
		{
			$uploader = new CloudinaryUploader( $this->_settings );

			// Mock the Cloudinary instance
			$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
			$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

			$mockCloudinary
				->method( 'uploadApi' )
				->willReturn( $mockUploadApi );

			$mockUploadApi
				->method( 'upload' )
				->willThrowException( new \Exception( 'Cloudinary API error' ) );

			// Use reflection to inject the mock
			$reflection = new \ReflectionClass( $uploader );
			$property = $reflection->getProperty( '_cloudinary' );
			$property->setAccessible( true );
			$property->setValue( $uploader, $mockCloudinary );

			$this->expectException( \Exception::class );
			$this->expectExceptionMessage( 'Cloudinary upload failed' );

			$uploader->upload( $testFile );
		}
		finally
		{
			if( file_exists( $testFile ) )
			{
				unlink( $testFile );
			}
		}
	}

	public function testUploadFromUrlThrowsExceptionOnCloudinaryError(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

		$mockCloudinary
			->method( 'uploadApi' )
			->willReturn( $mockUploadApi );

		$mockUploadApi
			->method( 'upload' )
			->willThrowException( new \Exception( 'Invalid image format' ) );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary upload from URL failed' );

		$uploader->uploadFromUrl( 'https://example.com/image.jpg' );
	}

	public function testDeleteThrowsExceptionOnCloudinaryError(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockUploadApi = $this->createMock( \Cloudinary\Api\Upload\UploadApi::class );

		$mockCloudinary
			->method( 'uploadApi' )
			->willReturn( $mockUploadApi );

		$mockUploadApi
			->method( 'destroy' )
			->willThrowException( new \Exception( 'API error' ) );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary deletion failed' );

		$uploader->delete( 'test-image' );
	}

	public function testListResourcesThrowsExceptionOnCloudinaryError(): void
	{
		$uploader = new CloudinaryUploader( $this->_settings );

		// Mock the Cloudinary instance
		$mockCloudinary = $this->createMock( \Cloudinary\Cloudinary::class );
		$mockAdminApi = $this->createMock( \Cloudinary\Api\Admin\AdminApi::class );

		$mockCloudinary
			->method( 'adminApi' )
			->willReturn( $mockAdminApi );

		$mockAdminApi
			->method( 'assets' )
			->willThrowException( new \Exception( 'Authentication failed' ) );

		// Use reflection to inject the mock
		$reflection = new \ReflectionClass( $uploader );
		$property = $reflection->getProperty( '_cloudinary' );
		$property->setAccessible( true );
		$property->setValue( $uploader, $mockCloudinary );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Cloudinary list resources failed' );

		$uploader->listResources();
	}
}
