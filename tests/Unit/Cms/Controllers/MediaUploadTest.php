<?php

namespace Tests\Cms\Controllers;

use PHPUnit\Framework\TestCase;
use Neuron\Cms\Controllers\Admin\Media;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Media\CloudinaryUploader;
use Neuron\Cms\Services\Media\MediaValidator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;

/**
 * Unit tests for Media controller upload methods
 */
class MediaUploadTest extends TestCase
{
	private array $_originalRegistry = [];
	private SettingManager $_settings;

	protected function setUp(): void
	{
		parent::setUp();

		// Store original registry values
		$this->_originalRegistry = [
			'Settings' => Registry::getInstance()->get( 'Settings' )
		];

		// Set up Settings
		$memory = new Memory();
		$memory->set( 'cloudinary', 'cloud_name', 'test-cloud' );
		$memory->set( 'cloudinary', 'api_key', 'test-key' );
		$memory->set( 'cloudinary', 'api_secret', 'test-secret' );
		$memory->set( 'cloudinary', 'folder', 'test-folder' );
		$memory->set( 'cloudinary', 'max_file_size', 5242880 );
		$memory->set( 'cloudinary', 'allowed_formats', ['jpg', 'png', 'gif', 'webp'] );

		$this->_settings = new SettingManager( $memory );
		Registry::getInstance()->set( 'Settings', $this->_settings );
	}

	protected function tearDown(): void
	{
		// Restore original registry values
		foreach( $this->_originalRegistry as $key => $value )
		{
			Registry::getInstance()->set( $key, $value );
		}

		// Clean up $_FILES if set
		$_FILES = [];

		parent::tearDown();
	}

	public function testUploadImageReturnsErrorWhenNoFileUploaded(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Ensure $_FILES is empty
		$_FILES = [];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );
		$request = $this->createMock( Request::class );
		$result = $media->uploadImage( $request );

		// Should return JSON error
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'success', $result );
		$this->assertStringContainsString( 'No file was uploaded', $result );

		$json = json_decode( $result, true );
		$this->assertEquals( 0, $json['success'] );
	}

	public function testUploadImageReturnsErrorWhenValidationFails(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up invalid file in $_FILES
		$_FILES['image'] = [
			'name' => 'test.txt',
			'type' => 'text/plain',
			'tmp_name' => '/tmp/phptest',
			'error' => UPLOAD_ERR_OK,
			'size' => 1000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that fails
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( false );
		$validatorMock->method( 'getFirstError' )->willReturn( 'Invalid file type' );

		// Inject mock validator via reflection
		$reflection = new \ReflectionClass( $media );
		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadImage( $request );

		// Should return JSON error
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertEquals( 0, $json['success'] );
		$this->assertEquals( 'Invalid file type', $json['message'] );
	}

	public function testUploadFeaturedImageReturnsErrorWhenNoFileUploaded(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Ensure $_FILES is empty
		$_FILES = [];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );
		$request = $this->createMock( Request::class );
		$result = $media->uploadFeaturedImage( $request );

		// Should return JSON error
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'success', $result );
		$this->assertStringContainsString( 'No file was uploaded', $result );

		$json = json_decode( $result, true );
		$this->assertFalse( $json['success'] );
	}

	public function testUploadFeaturedImageReturnsErrorWhenValidationFails(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up invalid file in $_FILES
		$_FILES['image'] = [
			'name' => 'test.exe',
			'type' => 'application/x-executable',
			'tmp_name' => '/tmp/phptest',
			'error' => UPLOAD_ERR_OK,
			'size' => 1000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that fails
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( false );
		$validatorMock->method( 'getFirstError' )->willReturn( 'File type not allowed' );

		// Inject mock validator via reflection
		$reflection = new \ReflectionClass( $media );
		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadFeaturedImage( $request );

		// Should return JSON error
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'File type not allowed', $json['error'] );
	}

	public function testUploadImageSuccessfulUpload(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up valid file in $_FILES
		$_FILES['image'] = [
			'name' => 'test.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => '/tmp/phptest.jpg',
			'error' => UPLOAD_ERR_OK,
			'size' => 1000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that passes
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( true );

		// Create a mock uploader
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'upload' )->willReturn( [
			'url' => 'https://res.cloudinary.com/test/image/upload/v1/test.jpg',
			'public_id' => 'test-folder/test',
			'width' => 800,
			'height' => 600,
			'format' => 'jpg',
			'bytes' => 50000
		] );

		// Inject mocks via reflection
		$reflection = new \ReflectionClass( $media );

		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadImage( $request );

		// Should return JSON success in Editor.js format
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertEquals( 1, $json['success'] );
		$this->assertArrayHasKey( 'file', $json );
		$this->assertEquals( 'https://res.cloudinary.com/test/image/upload/v1/test.jpg', $json['file']['url'] );
		$this->assertEquals( 800, $json['file']['width'] );
		$this->assertEquals( 600, $json['file']['height'] );
	}

	public function testUploadFeaturedImageSuccessfulUpload(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up valid file in $_FILES
		$_FILES['image'] = [
			'name' => 'featured.png',
			'type' => 'image/png',
			'tmp_name' => '/tmp/phptest.png',
			'error' => UPLOAD_ERR_OK,
			'size' => 2000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that passes
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( true );

		// Create a mock uploader
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'upload' )->willReturn( [
			'url' => 'https://res.cloudinary.com/test/image/upload/v1/featured.png',
			'public_id' => 'test-folder/featured',
			'width' => 1920,
			'height' => 1080,
			'format' => 'png',
			'bytes' => 150000,
			'resource_type' => 'image',
			'created_at' => '2024-01-01T00:00:00Z'
		] );

		// Inject mocks via reflection
		$reflection = new \ReflectionClass( $media );

		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadFeaturedImage( $request );

		// Should return JSON success
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertTrue( $json['success'] );
		$this->assertArrayHasKey( 'data', $json );
		$this->assertEquals( 'https://res.cloudinary.com/test/image/upload/v1/featured.png', $json['data']['url'] );
	}

	public function testUploadImageHandlesUploadException(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up valid file in $_FILES
		$_FILES['image'] = [
			'name' => 'test.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => '/tmp/phptest.jpg',
			'error' => UPLOAD_ERR_OK,
			'size' => 1000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that passes
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( true );

		// Create a mock uploader that throws exception
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'upload' )->willThrowException( new \Exception( 'Upload failed' ) );

		// Inject mocks via reflection
		$reflection = new \ReflectionClass( $media );

		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadImage( $request );

		// Should return JSON error with user-friendly message
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertEquals( 0, $json['success'] );
		$this->assertEquals( 'Upload failed. Please try again.', $json['message'] );
	}

	public function testUploadFeaturedImageHandlesUploadException(): void
	{
		// Set up user in registry
		$user = $this->createMock( User::class );
		$user->method( 'getId' )->willReturn( 1 );
		Registry::getInstance()->set( 'Auth.User', $user );

		// Set up valid file in $_FILES
		$_FILES['image'] = [
			'name' => 'test.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => '/tmp/phptest.jpg',
			'error' => UPLOAD_ERR_OK,
			'size' => 1000
		];

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );
		$mockCloudinaryUploader = $this->createMock( CloudinaryUploader::class );
		$mockMediaValidator = $this->createMock( MediaValidator::class );

		// Create Media controller
		$media = new Media( null, $mockCloudinaryUploader, $mockMediaValidator, $mockSettingManager, $mockSessionManager );

		// Create a mock validator that passes
		$validatorMock = $this->createMock( MediaValidator::class );
		$validatorMock->method( 'validate' )->willReturn( true );

		// Create a mock uploader that throws exception
		$uploaderMock = $this->createMock( CloudinaryUploader::class );
		$uploaderMock->method( 'upload' )->willThrowException( new \Exception( 'Cloudinary error' ) );

		// Inject mocks via reflection
		$reflection = new \ReflectionClass( $media );

		$validatorProperty = $reflection->getProperty( '_validator' );
		$validatorProperty->setAccessible( true );
		$validatorProperty->setValue( $media, $validatorMock );

		$uploaderProperty = $reflection->getProperty( '_uploader' );
		$uploaderProperty->setAccessible( true );
		$uploaderProperty->setValue( $media, $uploaderMock );

		$request = $this->createMock( Request::class );
		$result = $media->uploadFeaturedImage( $request );

		// Should return JSON error with user-friendly message
		$this->assertIsString( $result );
		$json = json_decode( $result, true );
		$this->assertFalse( $json['success'] );
		$this->assertEquals( 'Upload failed. Please try again.', $json['error'] );
	}

}
